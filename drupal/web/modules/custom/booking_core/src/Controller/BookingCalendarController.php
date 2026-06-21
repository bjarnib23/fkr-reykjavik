<?php

namespace Drupal\booking_core\Controller;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Controller for the booking calendar view and JSON feed.
 */
class BookingCalendarController extends ControllerBase {

  /**
   * Constructs a BookingCalendarController object.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   */
  public function __construct(
    private readonly RequestStack $requestStack,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new self(
      $container->get('request_stack'),
    );
  }

  /**
   * Returns the booking calendar page render array.
   */
  public function page(): array {
    return [
      '#markup' => '<div id="booking-calendar"></div>',
      '#attached' => [
        'library' => ['booking_core/booking_calendar'],
        'drupalSettings' => [
          'bookingCalendar' => [
            'feedUrl' => Url::fromRoute('booking_core.calendar_feed', [], [
              'absolute' => TRUE,
            ])->toString(),
          ],
        ],
      ],
    ];
  }

  /**
   * Returns bookings and blocked periods as a FullCalendar JSON feed.
   */
  public function feed(): CacheableJsonResponse {
    $request     = $this->requestStack->getCurrentRequest();
    $range_start = $request->query->get('start');
    $range_end   = $request->query->get('end');

    $query = $this->entityTypeManager()->getStorage('booking')->getQuery()
      ->accessCheck(FALSE);

    if ($range_start) {
      $query->condition('date', $range_start, '>=');
    }
    if ($range_end) {
      $query->condition('date', $range_end, '<');
    }

    $ids    = $query->execute();
    $events = [];

    /** @var \Drupal\booking_core\BookingInterface $booking */
    foreach ($this->entityTypeManager()->getStorage('booking')->loadMultiple($ids) as $booking) {
      $date = $booking->getDate();
      if (!$date) {
        continue;
      }
      $title = $booking->getName();
      if ($booking->getService()) {
        $title .= ' — ' . $booking->getService();
      }
      $events[] = [
        'title' => $title,
        'start' => $date,
        'url'   => Url::fromRoute('entity.booking.canonical', ['booking' => $booking->id()], ['absolute' => TRUE])->toString(),
      ];
    }

    $blocked_periods = $this->config('booking_core.settings')->get('blocked_periods');
    foreach ($blocked_periods as $period) {
      if (empty($period['date'])) {
        continue;
      }

      // Skip periods outside the requested window.
      if ($range_start && $period['date'] < substr($range_start, 0, 10)) {
        continue;
      }
      if ($range_end && $period['date'] >= substr($range_end, 0, 10)) {
        continue;
      }

      $event = [
        'display' => 'background',
        'color'   => '#ef4444',
        'title'   => $period['reason'] ?: (string) $this->t('Blocked'),
      ];

      if (!empty($period['all_day'])) {
        // FullCalendar all-day end is exclusive, so add one day.
        $end_dt = new \DateTime($period['date']);
        $end_dt->modify('+1 day');
        $event['start']  = $period['date'];
        $event['end']    = $end_dt->format('Y-m-d');
        $event['allDay'] = TRUE;
      }
      else {
        $event['start'] = $period['date'] . 'T' . ($period['start_time'] ?? '00:00');
        $event['end']   = $period['date'] . 'T' . ($period['end_time'] ?? '23:59');
      }

      $events[] = $event;
    }

    $cache = new CacheableMetadata();
    $cache->setCacheTags(['booking_list', 'config:booking_core.settings']);
    $cache->setCacheContexts(['url.query_args:start', 'url.query_args:end', 'user.permissions']);

    $response = new CacheableJsonResponse($events);
    $response->addCacheableDependency($cache);
    return $response;
  }

}
