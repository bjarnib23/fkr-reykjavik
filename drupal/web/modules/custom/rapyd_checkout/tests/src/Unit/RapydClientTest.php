<?php

namespace Drupal\Tests\rapyd_checkout\Unit;

use Drupal\rapyd_checkout\RapydClient;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for RapydClient.
 */
#[CoversClass(RapydClient::class)]
#[Group('rapyd_checkout')]
class RapydClientTest extends UnitTestCase {

  /**
   * Builds a RapydClient with the given credentials.
   */
  private function makeClient(
    string $access = 'acc',
    string $secret = 'sec',
    bool $sandbox = TRUE,
    ?ClientInterface $http = NULL,
  ): RapydClient {
    return new RapydClient(
      $access,
      $secret,
      $sandbox,
      $http ?? $this->createMock(ClientInterface::class),
      $this->createMock(LoggerInterface::class),
    );
  }

  /**
   * Tests that signature verification fails when API keys are empty.
   */
  public function testVerifyWebhookReturnsFalseWithEmptyKeys(): void {
    $client = $this->makeClient('', '');
    $this->assertFalse($client->verifyWebhook('body', 'salt', '123', 'sig', '/path'));
  }

  /**
   * Tests that a correctly computed HMAC signature is accepted.
   */
  public function testVerifyWebhookValidSignature(): void {
    $access = 'my_access';
    $secret = 'my_secret';
    $raw_body = '{"type":"PAYMENT_COMPLETED"}';
    $salt = 'abcdef12';
    $timestamp = '1700000000';
    $path = '/payment/notify/rapyd_checkout';

    $to_sign = 'post' . $path . $salt . $timestamp . $access . $secret . $raw_body;
    $signature = base64_encode(hash_hmac('sha256', $to_sign, $secret));

    $client = $this->makeClient($access, $secret);
    $this->assertTrue($client->verifyWebhook($raw_body, $salt, $timestamp, $signature, $path));
  }

  /**
   * Tests that an incorrect signature is rejected.
   */
  public function testVerifyWebhookInvalidSignature(): void {
    $client = $this->makeClient('acc', 'sec');
    $this->assertFalse($client->verifyWebhook('body', 'salt', '123', 'wrong', '/path'));
  }

  /**
   * Tests that sandbox mode does not bypass signature verification.
   */
  public function testVerifyWebhookAlwaysVerifiesInSandboxMode(): void {
    $client = $this->makeClient('acc', 'sec', sandbox: TRUE);
    $this->assertFalse($client->verifyWebhook('body', 'salt', '123', 'bad_sig', '/path'));
  }

  /**
   * Tests that createCheckout sends the amount in minor units.
   *
   * Validates §1.1: the caller is responsible for converting to minor units
   * before calling createCheckout(); the method must forward the integer
   * unchanged so Rapyd receives e.g. 9999 for $99.99 USD.
   */
  public function testCreateCheckoutForwardsMinorUnitsAmountToApi(): void {
    $redirect_url = 'https://sandboxapi.rapyd.net/hosted';
    $api_response = json_encode([
      'data' => [
        'redirect_url' => $redirect_url,
        'id' => 'chk_unit_test',
      ],
    ]);

    $captured_body = NULL;
    $http = $this->createMock(ClientInterface::class);
    $http->expects($this->once())
      ->method('request')
      ->with(
        'POST',
        $this->stringContains('/v1/checkout'),
        $this->callback(function (array $options) use (&$captured_body): bool {
          $captured_body = json_decode($options['body'], TRUE);
          return TRUE;
        })
      )
      ->willReturn(new Response(200, [], $api_response));

    $client = $this->makeClient('acc', 'sec', TRUE, $http);
    $result = $client->createCheckout(42, 9999, 'USD', 'US', 'https://return', 'https://cancel');

    $this->assertEquals(9999, $captured_body['amount'], 'Amount forwarded in minor units.');
    $this->assertEquals('USD', $captured_body['currency']);
    $this->assertEquals('rapyd-order-42', $captured_body['merchant_reference_id']);
    $this->assertEquals($redirect_url, $result['redirect_url']);
    $this->assertEquals('chk_unit_test', $result['checkout_id']);
  }

}
