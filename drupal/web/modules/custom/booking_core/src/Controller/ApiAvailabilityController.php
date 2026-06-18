<?php

namespace Drupal\booking_core\Controller;

use Drupal\booking_core\BookingHoldService;
use Drupal\booking_core\BookingSlotService;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns available time slots for the React booking frontend.
 */
class ApiAvailabilityController extends ControllerBase {

  /**
   * Constructs an ApiAvailabilityController object.
   *
   * @param \Drupal\booking_core\BookingSlotService $slotService
   *   The slot service.
   * @param \Drupal\booking_core\BookingHoldService $holdService
   *   The hold service.
   */
  public function __construct(
    private BookingSlotService $slotService,
    private BookingHoldService $holdService,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('booking_core.slot_service'),
      $container->get('booking_core.hold_service'),
    );
  }

  /**
   * Returns slot availability for a date.
   *
   * GET /api/fkr/availability?date=YYYY-MM-DD
   *
   * Response: [{"time": "10:00", "status": "available|held"}, ...]
   */
  public function availability(Request $request): JsonResponse {
    if ($request->getMethod() === 'OPTIONS') {
      return new JsonResponse([], 200, $this->cors());
    }

    $date = $request->query->get('date');
    if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
      return new JsonResponse(['error' => 'Invalid or missing date'], 400, $this->cors());
    }

    $available = $this->slotService->getAvailableSlots($date);
    $held      = array_flip($this->holdService->getHeldTimesForDate($date));

    $slots = [];
    foreach ($available as $time) {
      $slots[] = [
        'time'   => $time,
        'status' => isset($held[$time]) ? 'held' : 'available',
      ];
    }

    return new JsonResponse($slots, 200, $this->cors());
  }

  /**
   * Returns CORS headers.
   *
   * @return array<string, string>
   */
  private function cors(): array {
    return [
      'Access-Control-Allow-Origin'  => '*',
      'Access-Control-Allow-Methods' => 'GET, OPTIONS',
      'Access-Control-Allow-Headers' => 'Content-Type',
    ];
  }

}
