<?php

namespace Drupal\Tests\booking_core\Kernel;

use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\booking_core\Entity\Booking;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the Booking entity can be created, saved, and loaded.
 *
 * @group booking_core
 */
#[RunTestsInSeparateProcesses]
class BookingEntityTest extends EntityKernelTestBase {

  /**
   * Modules to enable.
   *
   * @var string[]
   */
  protected static $modules = ['booking_core', 'datetime'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('booking');
  }

  /**
   * Tests that a Booking entity can be created, saved, and reloaded.
   */
  public function testBookingCanBeCreatedAndLoaded(): void {
    $booking = Booking::create([
      'name'    => 'Jane Doe',
      'email'   => 'jane@example.com',
      'phone'   => '555-1234',
      'service' => 'Haircut',
      'date'    => '2026-06-01T10:00:00',
      'notes'   => 'Please confirm by email.',
    ]);

    $this->assertSame(SAVED_NEW, $booking->save());
    $this->assertNotEmpty($booking->id());

    $loaded = Booking::load($booking->id());
    $this->assertSame('Jane Doe', $loaded->get('name')->value);
    $this->assertSame('jane@example.com', $loaded->get('email')->value);
    $this->assertSame('Haircut', $loaded->get('service')->value);
    $this->assertSame('2026-06-01T10:00:00', $loaded->get('date')->value);
  }

  /**
   * Tests that a Booking without a name fails validation.
   */
  public function testBookingRequiresName(): void {
    $booking = Booking::create([
      'email' => 'jane@example.com',
      'date'  => '2026-06-01T10:00:00',
    ]);

    $violations = $booking->validate();
    $this->assertGreaterThan(0, $violations->count());
  }

}
