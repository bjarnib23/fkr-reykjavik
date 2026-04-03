<?php

namespace Drupal\fkr_booking\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\Core\Render\Markup;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Handles booking form submissions from the React frontend.
 */
class BookingController extends ControllerBase{

  /**
   * The mail manager.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected MailManagerInterface $mailManager;

    /**
     * BookingController constructor.
     */
    public function __construct (
        MailManagerInterface $mail_manager,
        EntityTypeManagerInterface $entity_type_manager,
        ConfigFactoryInterface $config_factory,
    )   {
        $this->mailManager = $mail_manager;
        $this->entityTypeManager = $entity_type_manager;
        $this->configFactory = $config_factory;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container): static {
        return new static(
            $container->get('plugin.manager.mail'),
            $container->get('entity_type.manager'),
            $container->get('config.factory'),
        );
    }

    /**
     * Accepts a booking POST request from the React frontend.
     */
    public function submit(Request $request): JsonResponse {
        // Handle CORS preflight request.
        if ($request->getMethod() === 'OPTIONS') {
            return new JsonResponse([], 200, $this->corsHeaders());
        }

        $data = json_decode($request->getContent(), TRUE);

        // Validate required fields.
        foreach (['name', 'email', 'phone', 'date'] as $field) {
            if (empty($data[$field])) {
                return new JsonResponse(
                    ['error' => "Missing required field: $field"],
                    400,
                    $this->corsHeaders()
                );
            }
        }

        // Check that the slot is available.
        $storage = $this->entityTypeManager->getStorage('node');

        $availability = $storage->loadByProperties([
            'type' => 'fkr_availability',
            'field_available_time' => $data['date'],
        ]);

        if (empty($availability)) {
            return new JsonResponse(
                ['error' => 'This time slot is not available.'],
                409,
                $this->corsHeaders()
            );
        }

        $existing_booking = $storage->loadByProperties([
            'type' => 'fkr_booking',
            'field_dagsetning' => $data['date'],
        ]);

        if (!empty($existing_booking)) {
            return new JsonResponse(
                ['error' => 'This time slot is already booked.'],
                409,
                $this->corsHeaders()
            );
        }

        // Create the booking node.
        $node = $this->entityTypeManager->getStorage('node')->create([
            'type' => 'fkr_booking',
            'title' => $data['name'],
            'field_email' => $data['email'],
            'field_phone' => $data['phone'],
            'field_hvad_viltu_panta' => $data['hvad_viltu_panta'] ?? '',
            'field_dagsetning' => $data['date'],
            'field_notes' => $data['notes'] ?? '',
            'field_status' => 'pending',
            'status' => 1,
        ]);
        $node->save();

        $langcode = $this->configFactory->get('system.site')->get('langcode');
        $admin_email = $this->configFactory->get('system.site')->get('mail');

        // Send confirmation email to the customer.
        $this->mailManager->mail(
            'fkr_booking',
            'booking_confirmation',
            $data['email'],
            $langcode,
            ['name' => $data['name'], 'date' => $data['date']],
        );

        // Send notification email to the admin.
        $this->mailManager->mail(
            'fkr_booking',
            'booking_notification',
            $admin_email,
            $langcode,
            [
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'date' => $data['date'],
                'hvad_viltu_panta' => $data['hvad_viltu_panta'] ?? '',
                'notes' => $data['notes'] ?? '',
            ],
        );
        
        return new JsonResponse(
            ['message' => 'Booking received successfully.'],
            201,
            $this->corsHeaders()
        );
    }

    /**
     * Returns CORS headers for the React frontend.
     */
    private function corsHeaders(): array {
        return [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'POST, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type'
        ];
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

        $rows = [];
        foreach ($nodes as $node) {
            $raw_date = $node->get('field_dagsetning')->value ?? '';
            $formatted_date = $raw_date ? date('D d M Y \a\t H:i', strtotime($raw_date)) : '—';
            $status = $node->get('field_status')->first()?->value ?? 'pending';

            $select  = '<select class="booking-status-select" data-nid="' . $node->id() . '">';
            foreach (['pending' => 'Pending', 'confirmed' => 'Confirmed', 'rejected' => 'Rejected'] as $val => $label) {
                $selected = $status === $val ? ' selected' : '';
                $select .= '<option value="' . $val . '"' . $selected . '>' . $label . '</option>';
            }
            $select .= '</select>';

            $rows[] = [
                $node->getTitle(),
                $node->get('field_email')->value,
                $formatted_date,
                $node->get('field_hvad_viltu_panta')->value,
                Markup::create($select),
                Markup::create(
                    \Drupal\Core\Link::fromTextAndUrl('View',
                    \Drupal\Core\Url::fromRoute('fkr_booking.booking_details', ['node' => $node->id()]))->toString() .
                    ' | ' . $node->toLink('Edit', 'edit-form')->toString()
                ),
            ];
        }

        return [
            '#type' => 'table',
            '#header' => ['Name', 'Email', 'Date', 'Item', 'Status', 'View'],
            '#rows' => $rows,
            '#empty' => 'No bookings yet.',
            '#attached' => [
                'library' => ['fkr_booking/admin_bookings'],
            ],
        ];
    }

    /**
     * Updates the status of a booking via AJAX.
     */
    public function updateStatus(NodeInterface $node, Request $request): JsonResponse {
        $data = json_decode($request->getContent(), TRUE);
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
            ['Name', $node->getTitle()],
            ['Email', $node->get('field_email')->value],
            ['Date', $node->get('field_dagsetning')->value],
            ['Item', $node->get('field_hvad_viltu_panta')->value],
            ['Notes', $node->get('field_notes')->value],
            ['Status', $node->get('field_status')->value],
        ];

        return [
            '#type' => 'table',
            '#header' => ['Field', 'Value'],
            '#rows' => $rows,
        ];
    }
}