<?php

namespace Drupal\booking_core;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Generates and validates available booking time slots.
 */
class BookingSlotService {

  /**
   * Constructs a BookingSlotService object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    private ConfigFactoryInterface $configFactory,
    private EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Returns available time slots for a given date.
   *
   * @param string|null $date
   *   Date string in Y-m-d format.
   *
   * @return array<string, string>
   *   Keyed by time string (H:i), e.g. ['09:00' => '09:00'].
   */
  public function getAvailableSlots(?string $date): array {
    if (!$date) {
      return [];
    }

    $config        = $this->configFactory->get('booking_core.settings');
    $open_days     = $config->get('open_days');
    $open_time     = $config->get('open_time');
    $close_time    = $config->get('close_time');
    $slot_duration = (int) $config->get('slot_duration');
    $weeks_ahead   = (int) $config->get('weeks_ahead');

    $utc         = new \DateTimeZone('UTC');
    $dt          = new \DateTime($date . ' 00:00:00', $utc);
    $day_of_week = (int) $dt->format('w');
    if (!in_array($day_of_week, array_map('intval', $open_days))) {
      return [];
    }

    $today    = new \DateTime('now', $utc);
    $max_date = new \DateTime("+{$weeks_ahead} weeks", $utc);
    if ($dt <= $today || $dt > $max_date) {
      return [];
    }

    $blocked_periods = $config->get('blocked_periods');
    $blocked_ranges  = [];
    foreach ($blocked_periods as $period) {
      if (($period['date'] ?? '') !== $date) {
        continue;
      }
      if (!empty($period['all_day'])) {
        return [];
      }
      if (!empty($period['start_time']) && !empty($period['end_time'])) {
        $blocked_ranges[] = ['start' => $period['start_time'], 'end' => $period['end_time']];
      }
    }

    $booked_times = $this->getBookedTimes($date);

    return $this->generateSlots($date, $open_time, $close_time, $slot_duration, $booked_times, $blocked_ranges);
  }

  /**
   * Returns booked time strings (H:i) for a given date.
   *
   * @param string $date
   *   Date string in Y-m-d format.
   *
   * @return string[]
   *   Array of booked time strings in H:i format.
   */
  public function getBookedTimes(string $date): array {
    $site_tz  = new \DateTimeZone($this->configFactory->get('system.date')->get('timezone.default') ?: 'UTC');
    $utc      = new \DateTimeZone('UTC');
    $start_dt = (new \DateTime($date . ' 00:00:00', $site_tz))->setTimezone($utc);
    $end_dt   = (new \DateTime($date . ' 23:59:59', $site_tz))->setTimezone($utc);

    $ids = $this->entityTypeManager->getStorage('booking')->getQuery()
      ->condition('date', $start_dt->format('Y-m-d\TH:i:s'), '>=')
      ->condition('date', $end_dt->format('Y-m-d\TH:i:s'), '<=')
      ->accessCheck(FALSE)
      ->execute();

    if (!$ids) {
      return [];
    }

    $times = [];
    foreach ($this->entityTypeManager->getStorage('booking')->loadMultiple($ids) as $b) {
      /** @var \Drupal\booking_core\BookingInterface $b */
      $stored = new \DateTime($b->get('date')->value, $utc);
      $stored->setTimezone($site_tz);
      $times[] = $stored->format('H:i');
    }

    return $times;
  }

  /**
   * Generates all slots between open and close time, excluding booked/blocked.
   *
   * @param string $date
   *   Date string in Y-m-d format.
   * @param string $open_time
   *   Opening time in H:i format.
   * @param string $close_time
   *   Closing time in H:i format.
   * @param int $slot_duration
   *   Slot duration in minutes.
   * @param string[] $booked_times
   *   Array of already-booked time strings in H:i format.
   * @param array<array{start: string, end: string}> $blocked_ranges
   *   Array of blocked time ranges with 'start' and 'end' keys.
   *
   * @return array<string, string>
   *   Available slots keyed by time string (H:i).
   */
  public function generateSlots(
    string $date,
    string $open_time,
    string $close_time,
    int $slot_duration,
    array $booked_times = [],
    array $blocked_ranges = [],
  ): array {
    $utc    = new \DateTimeZone('UTC');
    $slots  = [];
    $cursor = new \DateTime($date . ' ' . $open_time, $utc);
    $end    = new \DateTime($date . ' ' . $close_time, $utc);

    while ($cursor < $end) {
      $time = $cursor->format('H:i');
      if (!in_array($time, $booked_times) && !$this->isBlocked($time, $blocked_ranges)) {
        $slots[$time] = $time;
      }
      $cursor->modify("+{$slot_duration} minutes");
    }

    return $slots;
  }

  /**
   * Returns TRUE if the given time falls within any blocked range.
   *
   * @param string $time
   *   Time string in H:i format.
   * @param array<array{start: string, end: string}> $blocked_ranges
   *   Array of blocked time ranges with 'start' and 'end' keys.
   *
   * @return bool
   *   TRUE if the time is within a blocked range.
   */
  private function isBlocked(string $time, array $blocked_ranges): bool {
    foreach ($blocked_ranges as $range) {
      if ($time >= $range['start'] && $time < $range['end']) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
