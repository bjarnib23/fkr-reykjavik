<?php

namespace Drupal\Tests\booking_core\Kernel;

use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\booking_core\BookingSlotService;
use Drupal\booking_core\Entity\Booking;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for BookingSlotService::getAvailableSlots().
 *
 * @group booking_core
 */
#[CoversClass(BookingSlotService::class)]
#[Group('booking_core')]
#[RunTestsInSeparateProcesses]
class BookingSlotServiceKernelTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['booking_core', 'datetime'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('booking');
    $this->installConfig(['booking_core']);
    // Fix site timezone to UTC for predictable slot/UTC conversions.
    $this->config('system.date')->set('timezone.default', 'UTC')->save();
  }

  /**
   * Returns the next occurrence of a given weekday (0=Sun … 6=Sat).
   */
  private function nextWeekday(int $day, int $extra_weeks = 0): \DateTime {
    $names = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    $date  = new \DateTime('next ' . $names[$day]);
    if ($extra_weeks > 0) {
      $date->modify("+{$extra_weeks} weeks");
    }
    return $date;
  }

  /**
   * Returns the slot service from the container.
   */
  private function slotService(): BookingSlotService {
    return $this->container->get('booking_core.slot_service');
  }

  /**
   * An open weekday within the booking window returns the expected slots.
   */
  public function testOpenDayReturnsSlots(): void {
    // Monday — open by default.
    $date  = $this->nextWeekday(1);
    $slots = $this->slotService()->getAvailableSlots($date->format('Y-m-d'));

    $this->assertNotEmpty($slots);
    $this->assertArrayHasKey('09:00', $slots);
    $this->assertArrayHasKey('16:30', $slots);
    $this->assertArrayNotHasKey('17:00', $slots);
  }

  /**
   * A closed day (Saturday) returns an empty array.
   */
  public function testClosedDayReturnsEmpty(): void {
    // Saturday — closed by default.
    $date  = $this->nextWeekday(6);
    $slots = $this->slotService()->getAvailableSlots($date->format('Y-m-d'));

    $this->assertEmpty($slots);
  }

  /**
   * A past date returns an empty array.
   */
  public function testPastDateReturnsEmpty(): void {
    // 2020-01-06 is a Monday well in the past.
    $slots = $this->slotService()->getAvailableSlots('2020-01-06');

    $this->assertEmpty($slots);
  }

  /**
   * A date beyond the weeks_ahead window returns an empty array.
   */
  public function testBeyondWeeksAheadReturnsEmpty(): void {
    // Default weeks_ahead is 4; next Monday + 6 weeks is always outside.
    $date  = $this->nextWeekday(1, 6);
    $slots = $this->slotService()->getAvailableSlots($date->format('Y-m-d'));

    $this->assertEmpty($slots);
  }

  /**
   * Passing NULL returns an empty array without errors.
   */
  public function testNullDateReturnsEmpty(): void {
    $this->assertEmpty($this->slotService()->getAvailableSlots(NULL));
  }

  /**
   * An all-day blocked period returns an empty array for that date.
   */
  public function testAllDayBlockedPeriodReturnsEmpty(): void {
    // Tuesday.
    $date     = $this->nextWeekday(2);
    $date_str = $date->format('Y-m-d');

    $this->config('booking_core.settings')->set('blocked_periods', [
      [
        'date'       => $date_str,
        'all_day'    => TRUE,
        'start_time' => '',
        'end_time'   => '',
        'reason'     => 'Holiday',
      ],
    ])->save();

    $this->assertEmpty($this->slotService()->getAvailableSlots($date_str));
  }

  /**
   * A partial block removes only the overlapping slots.
   */
  public function testPartialBlockExcludesOnlyOverlappingSlots(): void {
    // Wednesday.
    $date     = $this->nextWeekday(3);
    $date_str = $date->format('Y-m-d');

    $this->config('booking_core.settings')->set('blocked_periods', [
      [
        'date'       => $date_str,
        'all_day'    => FALSE,
        'start_time' => '10:00',
        'end_time'   => '12:00',
        'reason'     => 'Staff meeting',
      ],
    ])->save();

    $slots = $this->slotService()->getAvailableSlots($date_str);
    $this->assertArrayHasKey('09:30', $slots);
    $this->assertArrayNotHasKey('10:00', $slots);
    $this->assertArrayNotHasKey('11:30', $slots);
    $this->assertArrayHasKey('12:00', $slots);
  }

  /**
   * A slot that already has a booking is excluded from available slots.
   *
   * Site timezone is UTC, so stored UTC time equals local time.
   */
  public function testAlreadyBookedSlotIsExcluded(): void {
    // Thursday.
    $date     = $this->nextWeekday(4);
    $date_str = $date->format('Y-m-d');

    Booking::create([
      'name'  => 'Existing Booking',
      'email' => 'existing@example.com',
      'date'  => $date_str . 'T09:00:00',
    ])->save();

    $slots = $this->slotService()->getAvailableSlots($date_str);
    $this->assertArrayNotHasKey('09:00', $slots);
    $this->assertArrayHasKey('09:30', $slots);
  }

  /**
   * Multiple bookings on the same day each remove their own slot.
   */
  public function testMultipleBookingsEachRemoveTheirSlot(): void {
    // Friday.
    $date     = $this->nextWeekday(5);
    $date_str = $date->format('Y-m-d');

    foreach (['09:00', '10:00', '11:00'] as $time) {
      Booking::create([
        'name'  => 'User',
        'email' => 'u@example.com',
        'date'  => $date_str . 'T' . $time . ':00',
      ])->save();
    }

    $slots = $this->slotService()->getAvailableSlots($date_str);
    $this->assertArrayNotHasKey('09:00', $slots);
    $this->assertArrayNotHasKey('10:00', $slots);
    $this->assertArrayNotHasKey('11:00', $slots);
    $this->assertArrayHasKey('09:30', $slots);
  }

  /**
   * getBookedTimes() returns the local time, not the UTC time, for non-UTC sites.
   *
   * A booking stored at 13:00 UTC on a site set to America/New_York (EDT = UTC-4)
   * should appear as 09:00 local, not 13:00.
   */
  public function testNonUtcTimezoneReturnsLocalTime(): void {
    $this->config('system.date')
      ->set('timezone.default', 'America/New_York')
      ->save();

    // 2026-06-15 is a Monday in summer (EDT = UTC-4).
    // 09:00 local = 13:00 UTC.
    $date    = '2026-06-15';
    $ny_tz   = new \DateTimeZone('America/New_York');
    $utc_tz  = new \DateTimeZone('UTC');
    $local   = new \DateTime($date . ' 09:00:00', $ny_tz);
    $utc_iso = $local->setTimezone($utc_tz)->format('Y-m-d\TH:i:s');

    Booking::create([
      'name'  => 'Timezone Test',
      'email' => 'tz@example.com',
      'date'  => $utc_iso,
    ])->save();

    $times = $this->slotService()->getBookedTimes($date);

    $this->assertContains('09:00', $times, 'Local time (09:00 EDT) must be returned.');
    $this->assertNotContains('13:00', $times, 'UTC time (13:00) must not be returned.');
  }

}
