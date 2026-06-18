<?php

namespace Drupal\booking_core\Controller;

use Drupal\booking_core\BookingHoldService;
use Drupal\booking_core\BookingSlotService;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Handles slot holds and booking submission for the React booking frontend.
 */
class ApiBookingController extends ControllerBase {

  /**
   * Constructs an ApiBookingController object.
   *
   * @param \Drupal\booking_core\BookingHoldService $holdService
   *   The hold service.
   * @param \Drupal\booking_core\BookingSlotService $slotService
   *   The slot service.
   * @param \Drupal\Core\Mail\MailManagerInterface $mailManager
   *   The mail manager.
   * @param \Drupal\Core\Lock\LockBackendInterface $lock
   *   The lock backend.
   * @param \Drupal\Core\Flood\FloodInterface $flood
   *   The flood service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   */
  public function __construct(
    private BookingHoldService $holdService,
    private BookingSlotService $slotService,
    private MailManagerInterface $mailManager,
    private LockBackendInterface $lock,
    private FloodInterface $flood,
    EntityTypeManagerInterface $entityTypeManager,
    ConfigFactoryInterface $configFactory,
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->setConfigFactory($configFactory);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('booking_core.hold_service'),
      $container->get('booking_core.slot_service'),
      $container->get('plugin.manager.mail'),
      $container->get('lock'),
      $container->get('flood'),
      $container->get('entity_type.manager'),
      $container->get('config.factory'),
    );
  }

  /**
   * Holds or releases a slot.
   *
   * POST /api/fkr/booking/hold  — body: {"datetime": "YYYY-MM-DDTHH:MM:SS"}
   * DELETE /api/fkr/booking/hold — body: {"token": "..."}
   */
  public function hold(Request $request): JsonResponse {
    if ($request->getMethod() === 'OPTIONS') {
      return new JsonResponse([], 200, $this->cors());
    }

    $data = json_decode($request->getContent(), TRUE) ?? [];

    if ($request->getMethod() === 'DELETE') {
      $token = $data['token'] ?? NULL;
      if ($token) {
        $this->holdService->releaseHold($token);
      }
      return new JsonResponse(['status' => 'released'], 200, $this->cors());
    }

    $datetime = $data['datetime'] ?? NULL;
    if (!$datetime || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/', $datetime)) {
      return new JsonResponse(['error' => 'Invalid datetime'], 400, $this->cors());
    }

    $result = $this->holdService->holdSlot($datetime);
    if ($result === FALSE) {
      return new JsonResponse(['error' => 'Slot is already held'], 409, $this->cors());
    }

    return new JsonResponse($result, 200, $this->cors());
  }

  /**
   * Releases a hold (used as a beacon on page unload).
   *
   * POST /api/fkr/booking/hold-release — body: {"token": "..."}
   */
  public function holdRelease(Request $request): JsonResponse {
    if ($request->getMethod() === 'OPTIONS') {
      return new JsonResponse([], 200, $this->cors());
    }

    $data  = json_decode($request->getContent(), TRUE) ?? [];
    $token = $data['token'] ?? NULL;
    if ($token) {
      $this->holdService->releaseHold($token);
    }

    return new JsonResponse(['status' => 'released'], 200, $this->cors());
  }

  /**
   * Submits a booking.
   *
   * POST /api/fkr/booking
   * Body: {name, email, phone, date: "YYYY-MM-DDTHH:MM:SS", service, notes}
   * The date is in site timezone; it is converted to UTC before storage.
   */
  public function submit(Request $request): JsonResponse {
    if ($request->getMethod() === 'OPTIONS') {
      return new JsonResponse([], 200, $this->cors());
    }

    $config       = $this->configFactory()->get('booking_core.settings');
    $flood_limit  = (int) ($config->get('flood_limit') ?: 10);
    $flood_window = (int) ($config->get('flood_window') ?: 3600);
    $ip           = $request->getClientIp();

    if (!$this->flood->isAllowed('booking_core_api_submit', $flood_limit, $flood_window, $ip)) {
      return new JsonResponse(
        ['error' => 'Too many booking attempts. Please try again later.'],
        429,
        $this->cors()
      );
    }

    $data = json_decode($request->getContent(), TRUE) ?? [];

    foreach (['name', 'email', 'date'] as $field) {
      if (empty($data[$field])) {
        return new JsonResponse(['error' => "Missing required field: $field"], 400, $this->cors());
      }
    }

    $site_tz   = $this->configFactory()->get('system.date')->get('timezone.default') ?: 'UTC';
    $dt        = new \DateTime($data['date'], new \DateTimeZone($site_tz));
    $date_only = $dt->format('Y-m-d');
    $time_only = $dt->format('H:i');
    $dt->setTimezone(new \DateTimeZone('UTC'));
    $iso_utc   = $dt->format('Y-m-d\TH:i:s');

    $slots = $this->slotService->getAvailableSlots($date_only);
    if (!isset($slots[$time_only])) {
      return new JsonResponse(['error' => 'This time slot is not available.'], 409, $this->cors());
    }

    $lock_key = 'booking_core_api_slot_' . $iso_utc;
    if (!$this->lock->acquire($lock_key, 15)) {
      return new JsonResponse(
        ['error' => 'Could not process your booking. Please try again.'],
        503,
        $this->cors()
      );
    }

    // Re-check inside the lock to prevent race conditions.
    $slots = $this->slotService->getAvailableSlots($date_only);
    if (!isset($slots[$time_only])) {
      $this->lock->release($lock_key);
      return new JsonResponse(['error' => 'That time slot was just taken.'], 409, $this->cors());
    }

    try {
      $booking = $this->entityTypeManager->getStorage('booking')->create([
        'name'    => $data['name'],
        'email'   => $data['email'],
        'phone'   => $data['phone'] ?? '',
        'service' => $data['service'] ?? '',
        'date'    => $iso_utc,
        'notes'   => $data['notes'] ?? '',
      ]);
      $booking->save();
    }
    catch (\Exception $e) {
      $this->lock->release($lock_key);
      $this->getLogger('booking_core')->error('API booking save failed: @msg', ['@msg' => $e->getMessage()]);
      return new JsonResponse(['error' => 'Could not save your booking. Please try again.'], 500, $this->cors());
    }

    $this->lock->release($lock_key);
    $this->flood->register('booking_core_api_submit', $flood_window, $ip);

    $langcode    = $this->configFactory()->get('system.site')->get('langcode');
    $admin_email = $config->get('admin_email') ?: $this->configFactory()->get('system.site')->get('mail');

    $params = [
      'name'    => $data['name'],
      'date'    => $iso_utc,
      'email'   => $data['email'],
      'phone'   => $data['phone'] ?? '',
      'service' => $data['service'] ?? '',
      'notes'   => $data['notes'] ?? '',
    ];

    $this->mailManager->mail('booking_core', 'confirmation', $data['email'], $langcode, $params);
    $this->mailManager->mail('booking_core', 'notification', $admin_email, $langcode, $params);

    return new JsonResponse(['message' => 'Booking received successfully.'], 201, $this->cors());
  }

  /**
   * Returns CORS headers.
   *
   * @return array<string, string>
   */
  private function cors(): array {
    return [
      'Access-Control-Allow-Origin'  => '*',
      'Access-Control-Allow-Methods' => 'GET, POST, DELETE, OPTIONS',
      'Access-Control-Allow-Headers' => 'Content-Type',
    ];
  }

}
