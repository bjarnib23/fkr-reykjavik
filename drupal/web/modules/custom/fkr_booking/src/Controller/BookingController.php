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
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Handles booking form submissions from the React frontend.
 */
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

  /**
   * POST /api/fkr/booking/hold  — hold a slot for 10 minutes
   * DELETE /api/fkr/booking/hold — release a hold by token
   */
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

    // POST — create a hold.
    $datetime = $data['datetime'] ?? NULL;
    if (!$datetime || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/', $datetime)) {
      return new JsonResponse(['error' => 'Invalid datetime'], 400, $this->corsHeaders());
    }

    $storage = $this->entityTypeManager->getStorage('node');

    $availability = $storage->loadByProperties([
      'type'                 => 'fkr_availability',
      'field_available_time' => $datetime,
    ]);

    if (empty($availability)) {
      return new JsonResponse(['error' => 'Slot not available'], 409, $this->corsHeaders());
    }

    $bookings = $storage->loadByProperties([
      'type'             => 'fkr_booking',
      'field_dagsetning' => $datetime,
    ]);

    if (!empty($bookings)) {
      return new JsonResponse(['error' => 'Slot already booked'], 409, $this->corsHeaders());
    }

    // Check for an existing hold on this datetime.
    $holds = \Drupal::keyValueExpirable('fkr_booking_holds');
    foreach ($holds->getAll() as $existing) {
      if ($existing === $datetime) {
        return new JsonResponse(['error' => 'Slot is currently held'], 409, $this->corsHeaders());
      }
    }

    $token   = \Drupal::service('uuid')->generate();
    $ttl     = 600; // 10 minutes
    $expires = time() + $ttl;
    $holds->setWithExpire($token, $datetime, $ttl);

    return new JsonResponse(['token' => $token, 'expires' => $expires], 200, $this->corsHeaders());
  }

  /**
   * POST /api/fkr/booking/hold-release — release by token (used on unload)
   */
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

  /**
   * Accepts a booking POST request from the React frontend.
   */
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

    $storage = $this->entityTypeManager->getStorage('node');

    $availability = $storage->loadByProperties([
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


    $booking = $storage->create([
      'type'                 => 'fkr_booking',
      'title'                => $data['name'],
      'field_email'          => $data['email'],
      'field_phone'          => $data['phone'] ?? '',
      'field_dagsetning'     => $data['date'],
      'field_hvad_viltu_panta' => $data['service'] ?? '',
      'field_notes'          => $data['notes'] ?? '',
      'field_status'         => 'pending',
      'status'               => 1,
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
      ],
    );

    return new JsonResponse(
      ['message' => 'Booking received successfully.'],
      201,
      $this->corsHeaders()
    );
  }

  /**
   * Admin list of all bookings, sorted by date.
   */
  public function adminList(): array {
    $nids = $this->entityTypeManager->getStorage('node')->getQuery()
      ->condition('type', 'fkr_booking')
      ->sort('field_dagsetning', 'ASC')
      ->accessCheck(FALSE)
      ->execute();

    $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($nids);
    $rows  = [];

    foreach ($nodes as $node) {
      $raw_date       = $node->get('field_dagsetning')->value ?? '';
      $formatted_date = $raw_date ? date('D d M Y \a\t H:i', strtotime($raw_date)) : '—';
      $status         = $node->get('field_status')->first()?->value ?? 'pending';

      $select = '<select class="booking-status-select" data-nid="' . $node->id() . '">';
      foreach (['pending' => 'Pending', 'confirmed' => 'Confirmed', 'rejected' => 'Rejected'] as $val => $label) {
        $selected = $status === $val ? ' selected' : '';
        $select  .= '<option value="' . $val . '"' . $selected . '>' . $label . '</option>';
      }
      $select .= '</select>';

      $rows[] = [
        $node->getTitle(),
        $node->get('field_email')->value,
        $formatted_date,
        $node->get('field_hvad_viltu_panta')->value,
        Markup::create($select),
        Markup::create(
          Link::fromTextAndUrl('View', Url::fromRoute('fkr_booking.booking_details', ['node' => $node->id()]))->toString()
          . ' | ' . $node->toLink('Edit', 'edit-form')->toString()
        ),
      ];
    }

    return [
      'add_button' => [
        '#type'       => 'link',
        '#title'      => '+ Add booking',
        '#url'        => Url::fromRoute('node.add', ['node_type' => 'fkr_booking']),
        '#attributes' => ['class' => ['button', 'button--primary']],
      ],
      'table' => [
        '#type'     => 'table',
        '#header'   => ['Name', 'Email', 'Date', 'Item', 'Status', 'View'],
        '#rows'     => $rows,
        '#empty'    => 'No bookings yet.',
        '#attached' => [
          'library' => ['fkr_booking/admin_bookings'],
        ],
      ],
    ];
  }

  /**
   * Updates the status of a booking via AJAX.
   */
  public function updateStatus(NodeInterface $node, Request $request): JsonResponse {
    $data   = json_decode($request->getContent(), TRUE);
    $status = $data['status'] ?? NULL;

    if (!in_array($status, ['pending', 'confirmed', 'rejected'])) {
      return new JsonResponse(['error' => 'Invalid status'], 400);
    }

    $node->set('field_status', $status);
    $node->save();

    return new JsonResponse(['status' => $status]);
  }

  /**
   * Admin detail view of a single booking.
   */
  public function bookingDetails(NodeInterface $node): array {
    $rows = [
      ['Name',   $node->getTitle()],
      ['Email',  $node->get('field_email')->value],
      ['Date',   $node->get('field_dagsetning')->value],
      ['Item',   $node->get('field_hvad_viltu_panta')->value],
      ['Notes',  $node->get('field_notes')->value],
      ['Status', $node->get('field_status')->value],
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
