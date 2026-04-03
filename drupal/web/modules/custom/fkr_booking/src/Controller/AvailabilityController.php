<?php

namespace Drupal\fkr_booking\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Handles availability calendar for admin and React frontend.
 */
class AvailabilityController extends ControllerBase {

  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  public static function create(ContainerInterface $container): static {
    return new static($container->get('entity_type.manager'));
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

    $days = [];
    for ($i = 0; $i < 7; $i++) {
      $day = clone $monday;
      $day->modify("+$i days");
      $date_str = $day->format('Y-m-d');
      $days[] = [
        'date'  => $date_str,
        'label' => $day->format('D d/m'),
        'slots' => $this->getSlotsForDate($date_str),
      ];
    }

    $time_slots = $this->generateTimeSlots();
    $prev = $week_offset - 1;
    $next = $week_offset + 1;

    $html  = '<div id="availability-calendar" data-week="' . $week_offset . '">';
    $html .= '<div class="cal-nav">';
    $html .= '<a href="?week=' . $prev . '" class="button">&#8592; Previous week</a>';
    $html .= '<a href="?week=' . $next . '" class="button">Next week &#8594;</a>';
    $html .= '</div>';
    $html .= '<table class="cal-table"><thead><tr><th>Time</th>';

    foreach ($days as $day) {
      $html .= '<th>' . $day['label'] . '</th>';
    }
    $html .= '</tr></thead><tbody>';

    foreach ($time_slots as $time) {
      $html .= '<tr><td class="cal-time">' . $time . '</td>';
      foreach ($days as $day) {
        $slot = $this->findSlot($day['slots'], $time);
        $status = $slot['status'];
        $datetime = $day['date'] . 'T' . $time . ':00';
        $label = $status === 'booked' ? ($slot['customer'] ?? 'Booked') : ucfirst($status);
        $clickable = $status !== 'booked' ? 'cal-clickable' : '';
        $html .= '<td class="cal-slot cal-' . $status . ' ' . $clickable . '" ';
        $html .= 'data-datetime="' . $datetime . '" title="' . $label . '">';
        $html .= '<span>' . $label . '</span>';
        $html .= '</td>';
      }
      $html .= '</tr>';
    }

    $html .= '</tbody></table>';
    $html .= '<div class="cal-legend">';
    $html .= '<span class="cal-slot cal-closed">Closed</span>';
    $html .= '<span class="cal-slot cal-available">Available</span>';
    $html .= '<span class="cal-slot cal-booked">Booked</span>';
    $html .= '</div>';
    $html .= '</div>';

    return [
      '#markup' => $html,
      '#attached' => [
        'library' => ['fkr_booking/availability_calendar'],
      ],
    ];
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
      'type' => 'fkr_booking',
      'field_dagsetning' => $datetime,
    ]);

    if (!empty($bookings)) {
      return new JsonResponse(['error' => 'Slot is booked and cannot be changed'], 409, $this->corsHeaders());
    }

    $existing = $storage->loadByProperties([
      'type' => 'fkr_availability',
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
   * Returns all 12 slots for a given date with their status.
   */
  private function getSlotsForDate(string $date): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $slots = [];

    foreach ($this->generateTimeSlots() as $time) {
      $datetime = $date . 'T' . $time . ':00';

      $availability = $storage->loadByProperties([
        'type'                 => 'fkr_availability',
        'field_available_time' => $datetime,
      ]);

      if (empty($availability)) {
        $slots[] = ['time' => $time, 'status' => 'closed'];
        continue;
      }

      $bookings = $storage->loadByProperties([
        'type'             => 'fkr_booking',
        'field_dagsetning' => $datetime,
      ]);

      if (!empty($bookings)) {
        $booking = reset($bookings);
        $slots[] = [
          'time'       => $time,
          'status'     => 'booked',
          'booking_id' => $booking->id(),
          'customer'   => $booking->getTitle(),
        ];
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

  private function findSlot(array $slots, string $time): array {
    foreach ($slots as $slot) {
      if ($slot['time'] === $time) {
        return $slot;
      }
    }
    return ['time' => $time, 'status' => 'closed'];
  }

  private function corsHeaders(): array {
    return [
      'Access-Control-Allow-Origin'  => '*',
      'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
      'Access-Control-Allow-Headers' => 'Content-Type',
    ];
  }

}