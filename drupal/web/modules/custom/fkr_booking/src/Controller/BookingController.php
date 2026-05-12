<?php

namespace Drupal\fkr_booking\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\fkr_booking\Entity\Booking;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class BookingController extends ControllerBase {

  protected MailManagerInterface $mailManager;
  protected LockBackendInterface $lock;

  public function __construct(
    MailManagerInterface $mail_manager,
    EntityTypeManagerInterface $entity_type_manager,
    ConfigFactoryInterface $config_factory,
    LockBackendInterface $lock,
  ) {
    $this->mailManager = $mail_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;
    $this->lock = $lock;
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('plugin.manager.mail'),
      $container->get('entity_type.manager'),
      $container->get('config.factory'),
      $container->get('lock'),
    );
  }

  public function hold(Request $request): JsonResponse {
    if ($request->getMethod() === 'OPTIONS') {
      return new JsonResponse([], 200, $this->corsHeaders());
    }

    $data = json_decode($request->getContent(), TRUE);

    if ($request->getMethod() === 'DELETE') {
      $token = $data['token'] ?? NULL;
      if ($token) {
        \Drupal::keyValueExpirable('fkr_booking_holds')->delete($token);
      }
      return new JsonResponse(['status' => 'released'], 200, $this->corsHeaders());
    }

    $datetime = $data['datetime'] ?? NULL;
    if (!$datetime || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/', $datetime)) {
      return new JsonResponse(['error' => 'Invalid datetime'], 400, $this->corsHeaders());
    }

    $availability = $this->entityTypeManager->getStorage('node')->loadByProperties([
      'type'                 => 'fkr_availability',
      'field_available_time' => $datetime,
    ]);

    if (empty($availability)) {
      return new JsonResponse(['error' => 'Slot not available'], 409, $this->corsHeaders());
    }

    $bookings = $this->entityTypeManager->getStorage('fkr_booking')->loadByProperties([
      'date' => $datetime,
    ]);

    if (!empty($bookings)) {
      return new JsonResponse(['error' => 'Slot already booked'], 409, $this->corsHeaders());
    }

    $holds = \Drupal::keyValueExpirable('fkr_booking_holds');
    foreach ($holds->getAll() as $existing) {
      if ($existing === $datetime) {
        return new JsonResponse(['error' => 'Slot is currently held'], 409, $this->corsHeaders());
      }
    }

    $token   = \Drupal::service('uuid')->generate();
    $ttl     = 600;
    $expires = time() + $ttl;
    $holds->setWithExpire($token, $datetime, $ttl);

    return new JsonResponse(['token' => $token, 'expires' => $expires], 200, $this->corsHeaders());
  }

  public function holdRelease(Request $request): JsonResponse {
    if ($request->getMethod() === 'OPTIONS') {
      return new JsonResponse([], 200, $this->corsHeaders());
    }

    $data  = json_decode($request->getContent(), TRUE);
    $token = $data['token'] ?? NULL;
    if ($token) {
      \Drupal::keyValueExpirable('fkr_booking_holds')->delete($token);
    }

    return new JsonResponse(['status' => 'released'], 200, $this->corsHeaders());
  }

  public function submit(Request $request): JsonResponse {
    if ($request->getMethod() === 'OPTIONS') {
      return new JsonResponse([], 200, $this->corsHeaders());
    }

    $data = json_decode($request->getContent(), TRUE);

    foreach (['name', 'email', 'phone', 'date'] as $field) {
      if (empty($data[$field])) {
        return new JsonResponse(
          ['error' => "Missing required field: $field"],
          400,
          $this->corsHeaders()
        );
      }
    }

    $availability = $this->entityTypeManager->getStorage('node')->loadByProperties([
      'type'                 => 'fkr_availability',
      'field_available_time' => $data['date'],
    ]);

    if (empty($availability)) {
      return new JsonResponse(
        ['error' => 'This time slot is not available.'],
        409,
        $this->corsHeaders()
      );
    }

    $lock_key = 'fkr_booking_slot_' . md5($data['date']);
    if (!$this->lock->acquire($lock_key)) {
      return new JsonResponse(
        ['error' => 'Could not process your booking. Please try again.'],
        503,
        $this->corsHeaders()
      );
    }

    $booking = $this->entityTypeManager->getStorage('fkr_booking')->create([
      'name'           => $data['name'],
      'email'          => $data['email'],
      'phone'          => $data['phone'] ?? '',
      'date'           => $data['date'],
      'service'        => $data['service'] ?? '',
      'notes'          => $data['notes'] ?? '',
      'wishes'         => $data['wishes'] ?? '',
      'booking_status' => 'pending',
    ]);
    $booking->save();

    $this->lock->release($lock_key);

    $langcode    = $this->configFactory->get('system.site')->get('langcode');
    $admin_email = $this->configFactory->get('system.site')->get('mail');

    $this->mailManager->mail(
      'fkr_booking',
      'booking_confirmation',
      $data['email'],
      $langcode,
      ['name' => $data['name'], 'date' => $data['date']],
    );

    $this->mailManager->mail(
      'fkr_booking',
      'booking_notification',
      $admin_email,
      $langcode,
      [
        'name'             => $data['name'],
        'email'            => $data['email'],
        'phone'            => $data['phone'] ?? '',
        'date'             => $data['date'],
        'hvad_viltu_panta' => $data['service'] ?? '',
        'notes'            => $data['notes'] ?? '',
        'wishes'           => $data['wishes'] ?? '',
      ],
    );

    return new JsonResponse(
      ['message' => 'Booking received successfully.'],
      201,
      $this->corsHeaders()
    );
  }

  public function adminList(): array {
    $ids = $this->entityTypeManager->getStorage('fkr_booking')->getQuery()
      ->sort('date', 'ASC')
      ->accessCheck(FALSE)
      ->execute();

    $bookings = $this->entityTypeManager->getStorage('fkr_booking')->loadMultiple($ids);
    $rows     = [];

    foreach ($bookings as $booking) {
      $raw_date       = $booking->get('date')->value ?? '';
      $formatted_date = $raw_date ? date('D d M Y \a\t H:i', strtotime($raw_date)) : '—';

      $rows[] = [
        $booking->get('name')->value,
        $booking->get('email')->value,
        $formatted_date,
        $booking->get('service')->value,
        Markup::create(
          Link::fromTextAndUrl('View', Url::fromRoute('fkr_booking.booking_details', ['fkr_booking' => $booking->id()]))->toString() .
          ' | ' .
          Link::fromTextAndUrl('Delete', Url::fromRoute('fkr_booking.booking_delete', ['fkr_booking' => $booking->id()]))->toString()
        ),
      ];
    }

    return [
      'table' => [
        '#type'   => 'table',
        '#header' => ['Name', 'Email', 'Date', 'Item', 'View'],
        '#rows'   => $rows,
        '#empty'  => 'No bookings yet.',
      ],
    ];
  }

  public function bookingDeleteConfirm(Booking $fkr_booking): array {
    return [
      '#markup' => Markup::create(
        '<p>Are you sure you want to delete the booking for <strong>' . htmlspecialchars($fkr_booking->get('name')->value) . '</strong> on ' . htmlspecialchars($fkr_booking->get('date')->value) . '?</p>' .
        '<a href="/admin/fkr/bookings/' . $fkr_booking->id() . '/delete/confirm" class="button button--danger">Yes, delete</a> ' .
        '<a href="/admin/fkr/bookings" class="button">Cancel</a>'
      ),
    ];
  }

  public function bookingDelete(Booking $fkr_booking): \Symfony\Component\HttpFoundation\RedirectResponse {
    $fkr_booking->delete();
    $this->messenger()->addStatus($this->t('Booking deleted.'));
    return new \Symfony\Component\HttpFoundation\RedirectResponse('/admin/fkr/bookings');
  }

  public function bookingDetails(Booking $fkr_booking): array {
    $rows = [
      ['Name',    $fkr_booking->get('name')->value],
      ['Email',   $fkr_booking->get('email')->value],
      ['Date',    $fkr_booking->get('date')->value],
      ['Item',    $fkr_booking->get('service')->value],
      ['Notes',   $fkr_booking->get('notes')->value],
      ['Wishes',  $fkr_booking->get('wishes')->value],
    ];

    return [
      '#type'   => 'table',
      '#header' => ['Field', 'Value'],
      '#rows'   => $rows,
    ];
  }

  private function corsHeaders(): array {
    return [
      'Access-Control-Allow-Origin'  => '*',
      'Access-Control-Allow-Methods' => 'POST, DELETE, OPTIONS',
      'Access-Control-Allow-Headers' => 'Content-Type',
    ];
  }

}
