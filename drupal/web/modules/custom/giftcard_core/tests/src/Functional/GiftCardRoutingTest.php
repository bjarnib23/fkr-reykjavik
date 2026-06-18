<?php

namespace Drupal\Tests\giftcard_core\Functional;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests route access control and HTTP method restrictions.
 */
#[RunTestsInSeparateProcesses]
#[Group('giftcard_core')]
class GiftCardRoutingTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['giftcard_core', 'options'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests that the webhook endpoint rejects GET requests with HTTP 405.
   */
  public function testWebhookEndpointRejectsGet(): void {
    $this->drupalGet('/gift-card/webhook');
    $this->assertSession()->statusCodeEquals(405);
  }

  /**
   * Tests that anonymous users receive HTTP 403 on the settings page.
   */
  public function testSettingsPageDeniesAnonymousAccess(): void {
    $this->drupalGet('/admin/config/giftcard-core/settings');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests that a user with the administer gift cards permission can open settings.
   */
  public function testSettingsPageGrantsAccessWithPermission(): void {
    $account = $this->drupalCreateUser(['administer gift cards']);
    $this->drupalLogin($account);
    $this->drupalGet('/admin/config/giftcard-core/settings');
    $this->assertSession()->statusCodeEquals(200);
  }

}
