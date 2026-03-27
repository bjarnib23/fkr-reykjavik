<?php

namespace Drupal\fkr_booking\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Mail\MailManagerInterface;
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
     * Admin list of all bookings.
     */
    public function adminList(): array {
        $nodes = $this->entityTypeManager->getStorage('node')->loadByProperties([
            'type' => 'fkr_booking',
        ]);

        $rows = [];
        foreach ($nodes as $node) {
            $rows[] = [
                $node->getTitle(),
                $node->get('field_email')->value,
                $node->get('field_dagsetning')->value,
                $node->get('field_hvad_viltu_panta')->value,
                $node->get('field_status')->value,
                $node->toLink('Edit', 'edit-form')->toString(),
            ];
        }

        return [
            '#type' => 'table',
            '#header' => ['Name', 'Email', 'Date', 'Item', 'Status', 'Operations'],
            '#rows' => $rows,
            '#empty' => 'No bookings yet.'
        ];
    }
}