<?php

namespace Drupal\Tests\booking_core\Unit;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\booking_core\BookingSlotService;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Unit tests for BookingSlotService.
 */
#[CoversClass(BookingSlotService::class)]
#[Group('booking_core')]
class BookingSlotServiceTest extends UnitTestCase {

  /**
   * Builds a BookingSlotService with mocked dependencies.
   */
  private function makeService(): BookingSlotService {
    $config_factory  = $this->createMock(ConfigFactoryInterface::class);
    $entity_type_mgr = $this->createMock(EntityTypeManagerInterface::class);
    return new BookingSlotService($config_factory, $entity_type_mgr);
  }

  /**
   * Tests that a full work day generates the expected number of slots.
   */
  public function testGeneratesSlotsForFullDay(): void {
    $service = $this->makeService();
    $slots   = $service->generateSlots('2099-06-02', '09:00', '17:00', 60);

    $this->assertCount(8, $slots);
    $this->assertArrayHasKey('09:00', $slots);
    $this->assertArrayHasKey('16:00', $slots);
    $this->assertArrayNotHasKey('17:00', $slots);
  }

  /**
   * Tests that already-booked times are excluded from the slot list.
   */
  public function testExcludesBookedTimes(): void {
    $service = $this->makeService();
    $slots   = $service->generateSlots('2099-06-02', '09:00', '11:00', 60, ['09:00']);

    $this->assertArrayNotHasKey('09:00', $slots);
    $this->assertArrayHasKey('10:00', $slots);
  }

  /**
   * Tests that an empty array is returned when all slots are booked.
   */
  public function testReturnsEmptyWhenAllBooked(): void {
    $service = $this->makeService();
    $slots   = $service->generateSlots('2099-06-02', '09:00', '10:00', 60, ['09:00']);

    $this->assertEmpty($slots);
  }

  /**
   * Tests that 30-minute slots are generated correctly.
   */
  public function testThirtyMinuteSlots(): void {
    $service = $this->makeService();
    $slots   = $service->generateSlots('2099-06-02', '09:00', '10:00', 30);

    $this->assertCount(2, $slots);
    $this->assertArrayHasKey('09:00', $slots);
    $this->assertArrayHasKey('09:30', $slots);
  }

  /**
   * Tests that slots overlapping a blocked range are excluded.
   */
  public function testBlockedRangeExcludesOverlappingSlots(): void {
    $service = $this->makeService();
    $blocked = [['start' => '10:00', 'end' => '12:00']];
    $slots   = $service->generateSlots('2099-06-02', '09:00', '13:00', 60, [], $blocked);

    $this->assertArrayHasKey('09:00', $slots);
    $this->assertArrayNotHasKey('10:00', $slots);
    $this->assertArrayNotHasKey('11:00', $slots);
    $this->assertArrayHasKey('12:00', $slots);
  }

  /**
   * Tests that multiple blocked ranges each exclude the correct slots.
   */
  public function testMultipleBlockedRangesExcludeCorrectSlots(): void {
    $service = $this->makeService();
    $blocked = [
      ['start' => '09:00', 'end' => '10:00'],
      ['start' => '12:00', 'end' => '13:00'],
    ];
    $slots = $service->generateSlots('2099-06-02', '09:00', '14:00', 60, [], $blocked);

    $this->assertArrayNotHasKey('09:00', $slots);
    $this->assertArrayHasKey('10:00', $slots);
    $this->assertArrayHasKey('11:00', $slots);
    $this->assertArrayNotHasKey('12:00', $slots);
    $this->assertArrayHasKey('13:00', $slots);
  }

}
