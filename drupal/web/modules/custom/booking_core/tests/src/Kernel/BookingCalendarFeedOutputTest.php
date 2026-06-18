<?php

namespace Drupal\Tests\booking_core\Kernel;

use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\booking_core\Controller\BookingCalendarController;
use Drupal\booking_core\Entity\Booking;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests BookingCalendarController::feed() JSON output.
 *
 * @group booking_core
 */
#[CoversClass(BookingCalendarController::class)]
#[Group('booking_core')]
#[RunTestsInSeparateProcesses]
class BookingCalendarFeedOutputTest extends EntityKernelTestBase {

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
    $this->config('system.date')->set('timezone.default', 'UTC')->save();
  }

  /**
   * Sets start/end query params on the current kernel test request.
   */
  private function setFeedRange(string $start, string $end): void {
    $request = $this->container->get('request_stack')->getCurrentRequest();
    $request->query->set('start', $start);
    $request->query->set('end', $end);
  }

  /**
   * Feed JSON contains a booking with the correct title and start fields.
   */
  public function testFeedContainsCreatedBooking(): void {
    Booking::create([
      'name'    => 'Alice Smith',
      'email'   => 'alice@example.com',
      'service' => 'Test Tour',
      'date'    => '2026-06-15T09:00:00',
    ])->save();

    $this->setFeedRange('2026-06-01T00:00:00', '2026-07-01T00:00:00');

    $controller = BookingCalendarController::create($this->container);
    $events     = json_decode($controller->feed()->getContent(), TRUE);

    $this->assertCount(1, $events);
    $this->assertSame('Alice Smith — Test Tour', $events[0]['title']);
    $this->assertSame('2026-06-15T09:00:00', $events[0]['start']);
  }

  /**
   * Feed respects the date range — bookings outside the window are excluded.
   */
  public function testFeedExcludesBookingsOutsideRange(): void {
    Booking::create([
      'name'  => 'Inside Range',
      'email' => 'in@example.com',
      'date'  => '2026-06-15T09:00:00',
    ])->save();

    Booking::create([
      'name'  => 'Outside Range',
      'email' => 'out@example.com',
      'date'  => '2026-08-01T09:00:00',
    ])->save();

    $this->setFeedRange('2026-06-01T00:00:00', '2026-07-01T00:00:00');

    $controller = BookingCalendarController::create($this->container);
    $events     = json_decode($controller->feed()->getContent(), TRUE);

    $this->assertCount(1, $events);
    $this->assertSame('Inside Range', $events[0]['title']);
  }

}
