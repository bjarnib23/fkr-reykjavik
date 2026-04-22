<?php

namespace Drupal\fkr_booking\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Handles availability calendar for admin and React frontend.
 */
class AvailabilityController extends ControllerBase {

  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    protected Connection $database,
  ) {
    $this->entityTypeManager = $entity_type_manager;
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('database'),
    );
  }

  /**
   * GET /api/fkr/availability?date=YYYY-MM-DD
   */
  public function getAvailability(Request $request): JsonResponse {
    if ($request->getMethod() === 'OPTIONS') {
      return new JsonResponse([], 200, $this->corsHeaders());
    }

    $date = $request->query->get('date');
    if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
      return new JsonResponse(['error' => 'Invalid or missing date'], 400, $this->corsHeaders());
    }

    return new JsonResponse($this->getSlotsForDate($date), 200, $this->corsHeaders());
  }

  /**
   * Admin calendar page at /admin/fkr/availability.
   */
  public function adminCalendar(Request $request): array {
    $week_offset = (int) $request->query->get('week', 0);

    $monday = new \DateTime('monday this week');
    if ($week_offset !== 0) {
      $monday->modify("$week_offset weeks");
    }

    $time_slots = $this->generateTimeSlots();
    $days = [];

    for ($i = 0; $i < 7; $i++) {
      $day = clone $monday;
      $day->modify("+$i days");
      $date_str = $day->format('Y-m-d');
      $slots = $this->getSlotsForDate($date_str, TRUE);

      // Index slots by time for easy lookup in the template.
      $slot_map = [];
      foreach ($slots as $slot) {
        $slot_map[$slot['time']] = $slot;
      }

      $days[] = [
        'date'     => $date_str,
        'label'    => $day->format('D d/m'),
        'slots'    => $slots,
        'slot_map' => $slot_map,
      ];
    }

    return [
      '#theme'       => 'fkr_availability_calendar',
      '#week_offset' => $week_offset,
      '#prev'        => $week_offset - 1,
      '#next'        => $week_offset + 1,
      '#days'        => $days,
      '#time_slots'  => $time_slots,
      '#attached'    => [
        'library' => ['fkr_booking/availability_calendar'],
      ],
    ];
  }

  /**
   * POST /api/fkr/booking/hold-release — for sendBeacon on page unload.
   */
  public function releaseHoldBeacon(Request $request): JsonResponse {
    if ($request->getMethod() === 'OPTIONS') {
      return new JsonResponse([], 200, $this->corsHeaders());
    }
    $data = json_decode($request->getContent(), TRUE);
    $token = $data['token'] ?? NULL;
    if ($token) {
      $this->database->delete('fkr_slot_holds')->condition('token', $token)->execute();
    }
    return new JsonResponse(['status' => 'released'], 200, $this->corsHeaders());
  }

  /**
   * POST /api/fkr/booking/hold  — temporarily hold a slot.
   * DELETE /api/fkr/booking/hold — release a hold by token.
   */
  public function holdSlot(Request $request): JsonResponse {
    if ($request->getMethod() === 'OPTIONS') {
      return new JsonResponse([], 200, $this->corsHeaders());
    }

    $data = json_decode($request->getContent(), TRUE);

    if ($request->getMethod() === 'DELETE') {
      $token = $data['token'] ?? NULL;
      if ($token) {
        $this->database->delete('fkr_slot_holds')->condition('token', $token)->execute();
      }
      return new JsonResponse(['status' => 'released'], 200, $this->corsHeaders());
    }

    $datetime = $data['datetime'] ?? NULL;
    if (!$datetime || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/', $datetime)) {
      return new JsonResponse(['error' => 'Invalid datetime'], 400, $this->corsHeaders());
    }

    // Clean up expired holds.
    $this->database->delete('fkr_slot_holds')->condition('expires', time(), '<')->execute();

    // Check if slot is already held or booked.
    $active = $this->database->select('fkr_slot_holds', 'h')
      ->fields('h', ['id'])
      ->condition('datetime', $datetime)
      ->condition('expires', time(), '>=')
      ->countQuery()->execute()->fetchField();

    if ($active > 0) {
      return new JsonResponse(['error' => 'Slot is already held'], 409, $this->corsHeaders());
    }

    $hold_minutes = \Drupal::config('fkr_booking.settings')->get('hold_minutes') ?? 6;
    $expires = time() + ($hold_minutes * 60);
    $token = bin2hex(random_bytes(16));

    $this->database->insert('fkr_slot_holds')->fields([
      'datetime' => $datetime,
      'token'    => $token,
      'expires'  => $expires,
    ])->execute();

    return new JsonResponse([
      'token'   => $token,
      'expires' => $expires,
    ], 200, $this->corsHeaders());
  }

  /**
   * POST /api/fkr/availability/toggle
   */
  public function toggleSlot(Request $request): JsonResponse {
    if ($request->getMethod() === 'OPTIONS') {
      return new JsonResponse([], 200, $this->corsHeaders());
    }

    $data = json_decode($request->getContent(), TRUE);
    $datetime = $data['datetime'] ?? NULL;

    if (!$datetime || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/', $datetime)) {
      return new JsonResponse(['error' => 'Invalid datetime'], 400, $this->corsHeaders());
    }

    $storage = $this->entityTypeManager->getStorage('node');

    $bookings = $storage->loadByProperties([
      'type'             => 'fkr_booking',
      'field_dagsetning' => $datetime,
    ]);

    if (!empty($bookings)) {
      return new JsonResponse(['error' => 'Slot is booked and cannot be changed'], 409, $this->corsHeaders());
    }

    $existing = $storage->loadByProperties([
      'type'                 => 'fkr_availability',
      'field_available_time' => $datetime,
    ]);

    if (!empty($existing)) {
      foreach ($existing as $node) {
        $node->delete();
      }
      return new JsonResponse(['status' => 'closed'], 200, $this->corsHeaders());
    }

    $node = $storage->create([
      'type'                 => 'fkr_availability',
      'title'                => 'Slot ' . $datetime,
      'field_available_time' => $datetime,
      'status'               => 1,
    ]);
    $node->save();

    return new JsonResponse(['status' => 'available'], 200, $this->corsHeaders());
  }

  /**
   * Returns all slots for a given date with their status.
   */
  private function getSlotsForDate(string $date, bool $include_customer = FALSE): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $time_slots = $this->generateTimeSlots();

    // Build all datetimes for this date.
    $datetimes = array_map(fn($t) => $date . 'T' . $t . ':00', $time_slots);

    // Batch load all availability and bookings for the day.
    $availability_nids = $storage->getQuery()
      ->condition('type', 'fkr_availability')
      ->condition('field_available_time', $datetimes, 'IN')
      ->accessCheck(FALSE)
      ->execute();

    $booking_nids = $storage->getQuery()
      ->condition('type', 'fkr_booking')
      ->condition('field_dagsetning', $datetimes, 'IN')
      ->accessCheck(FALSE)
      ->execute();

    $available_times = [];
    foreach ($storage->loadMultiple($availability_nids) as $node) {
      $available_times[] = $node->get('field_available_time')->value;
    }

    $booked_times = [];
    foreach ($storage->loadMultiple($booking_nids) as $node) {
      $booked_times[$node->get('field_dagsetning')->value] = $node->getTitle();
    }

    // Collect active holds for this date.
    $held_times = $this->database->select('fkr_slot_holds', 'h')
      ->fields('h', ['datetime'])
      ->condition('datetime', $datetimes, 'IN')
      ->condition('expires', time(), '>=')
      ->execute()->fetchCol();

    $slots = [];
    foreach ($time_slots as $time) {
      $datetime = $date . 'T' . $time . ':00';

      if (!in_array($datetime, $available_times)) {
        $slots[] = ['time' => $time, 'status' => 'closed'];
      }
      elseif (isset($booked_times[$datetime])) {
        $slot = ['time' => $time, 'status' => 'booked'];
        if ($include_customer) {
          $slot['customer'] = $booked_times[$datetime];
        }
        $slots[] = $slot;
      }
      elseif (in_array($datetime, $held_times)) {
        $slots[] = ['time' => $time, 'status' => 'held'];
      }
      else {
        $slots[] = ['time' => $time, 'status' => 'available'];
      }
    }

    return $slots;
  }

  /**
   * Generates time slots from 10:00 to 15:30 every 30 minutes.
   */
  private function generateTimeSlots(): array {
    $slots = [];
    $start = strtotime('10:00');
    $end   = strtotime('16:00');
    for ($t = $start; $t < $end; $t += 1800) {
      $slots[] = date('H:i', $t);
    }
    return $slots;
  }

  private function corsHeaders(): array {
    return [
      'Access-Control-Allow-Origin'  => '*',
      'Access-Control-Allow-Methods' => 'GET, POST, DELETE, OPTIONS',
      'Access-Control-Allow-Headers' => 'Content-Type',
    ];
  }

}
