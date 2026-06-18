<?php

namespace Drupal\Tests\booking_core\Kernel;

use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Verifies that calendar feed routes enforce the correct permissions.
 *
 * @group booking_core
 */
#[Group('booking_core')]
#[RunTestsInSeparateProcesses]
class BookingCalendarFeedAccessTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['booking_core', 'datetime'];

  /**
   * The calendar feed route requires the 'manage booking' permission.
   */
  public function testCalendarFeedRouteRequiresManageBookingPermission(): void {
    $route = $this->container->get('router.route_provider')
      ->getRouteByName('booking_core.calendar_feed');

    $this->assertEquals('manage booking', $route->getRequirement('_permission'));
  }

  /**
   * The calendar page route requires the 'manage booking' permission.
   */
  public function testCalendarPageRouteRequiresManageBookingPermission(): void {
    $route = $this->container->get('router.route_provider')
      ->getRouteByName('booking_core.calendar');

    $this->assertEquals('manage booking', $route->getRequirement('_permission'));
  }

  /**
   * The settings route requires the 'administer booking' permission.
   */
  public function testSettingsRouteRequiresAdministerBookingPermission(): void {
    $route = $this->container->get('router.route_provider')
      ->getRouteByName('booking_core.settings');

    $this->assertEquals('administer booking', $route->getRequirement('_permission'));
  }

  /**
   * Both 'administer booking' and 'manage booking' exist as defined permissions.
   */
  public function testBothPermissionsAreDefined(): void {
    $permissions = $this->container->get('user.permissions')->getPermissions();

    $this->assertArrayHasKey('administer booking', $permissions);
    $this->assertArrayHasKey('manage booking', $permissions);
  }

}
