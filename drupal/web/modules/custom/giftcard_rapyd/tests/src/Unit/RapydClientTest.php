<?php

namespace Drupal\Tests\giftcard_rapyd\Unit;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\giftcard_rapyd\RapydClient;
use Drupal\key\KeyInterface;
use Drupal\key\KeyRepositoryInterface;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\ClientInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;

/**
 * Unit tests for RapydClient.
 */
#[CoversClass(RapydClient::class)]
#[Group('giftcard_rapyd')]
class RapydClientTest extends UnitTestCase {

  /**
   * Builds a RapydClient wired with specific access/secret key values.
   *
   * @param string $access
   *   The access key value.
   * @param string $secret
   *   The secret key value.
   * @param \Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface|null $saltStore
   *   Optional mock for the seen-salts key-value store.
   *
   * @return \Drupal\giftcard_rapyd\RapydClient
   *   The client under test.
   */
  private function makeClient(string $access = 'acc', string $secret = 'sec', ?KeyValueStoreExpirableInterface $saltStore = NULL): RapydClient {
    $accessKey = $this->createMock(KeyInterface::class);
    $accessKey->method('getKeyValue')->willReturn($access);
    $secretKey = $this->createMock(KeyInterface::class);
    $secretKey->method('getKeyValue')->willReturn($secret);

    $keyRepo = $this->createMock(KeyRepositoryInterface::class);
    $keyRepo->method('getKey')->willReturnMap([
      ['access_id', $accessKey],
      ['secret_id', $secretKey],
    ]);

    $settings = $this->createMock(ImmutableConfig::class);
    $settings->method('get')->willReturnMap([
      ['rapyd_access_key_id', 'access_id'],
      ['rapyd_secret_key_id', 'secret_id'],
      ['rapyd_sandbox', TRUE],
      ['rapyd_country', 'IS'],
    ]);
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->willReturn($settings);

    $logger = $this->createMock(LoggerChannelInterface::class);
    $loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $loggerFactory->method('get')->willReturn($logger);

    if ($saltStore === NULL) {
      $saltStore = $this->createMock(KeyValueStoreExpirableInterface::class);
      $saltStore->method('get')->willReturn(NULL);
    }
    $keyValueExpirable = $this->createMock(KeyValueExpirableFactoryInterface::class);
    $keyValueExpirable->method('get')->willReturn($saltStore);

    return new RapydClient(
      $this->createMock(ClientInterface::class),
      $keyRepo,
      $configFactory,
      $loggerFactory,
      $this->createMock(LanguageManagerInterface::class),
      $keyValueExpirable,
    );
  }

  /**
   * Builds a RapydClient with empty (unconfigured) credentials.
   *
   * @return \Drupal\giftcard_rapyd\RapydClient
   *   The client under test.
   */
  private function makeEmptyKeysClient(): RapydClient {
    $settings = $this->createMock(ImmutableConfig::class);
    $settings->method('get')->willReturnMap([
      ['rapyd_access_key_id', ''],
      ['rapyd_secret_key_id', ''],
      ['rapyd_sandbox', TRUE],
    ]);
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->willReturn($settings);

    $logger = $this->createMock(LoggerChannelInterface::class);
    $loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $loggerFactory->method('get')->willReturn($logger);

    $saltStore = $this->createMock(KeyValueStoreExpirableInterface::class);
    $keyValueExpirable = $this->createMock(KeyValueExpirableFactoryInterface::class);
    $keyValueExpirable->method('get')->willReturn($saltStore);

    return new RapydClient(
      $this->createMock(ClientInterface::class),
      $this->createMock(KeyRepositoryInterface::class),
      $configFactory,
      $loggerFactory,
      $this->createMock(LanguageManagerInterface::class),
      $keyValueExpirable,
    );
  }

  /**
   * Builds a Symfony Request with the Rapyd signature headers.
   *
   * @param string $body
   *   The raw request body.
   * @param string $salt
   *   The idempotency key.
   * @param string $timestamp
   *   The Unix timestamp string.
   * @param string $signature
   *   The HMAC-SHA256 signature.
   * @param string $path
   *   The request path.
   *
   * @return \Symfony\Component\HttpFoundation\Request
   *   The constructed request.
   */
  private function makeRequest(string $body, string $salt, string $timestamp, string $signature, string $path = '/gift-card/webhook'): Request {
    $request = Request::create($path, 'POST', [], [], [], [], $body);
    $request->headers->set('rapyd-idempotency', $salt);
    $request->headers->set('rapyd-timestamp', $timestamp);
    $request->headers->set('rapyd-signature', $signature);
    return $request;
  }

  /**
   * Computes the expected Rapyd HMAC signature.
   *
   * @param string $path
   *   The request path.
   * @param string $salt
   *   The idempotency key.
   * @param string $timestamp
   *   The Unix timestamp string.
   * @param string $access
   *   The access key.
   * @param string $secret
   *   The secret key.
   * @param string $body
   *   The raw request body.
   *
   * @return string
   *   The base64-encoded HMAC-SHA256 signature.
   */
  private function sign(string $path, string $salt, string $timestamp, string $access, string $secret, string $body): string {
    $toSign = 'post' . $path . $salt . $timestamp . $access . $secret . $body;
    return base64_encode(hash_hmac('sha256', $toSign, $secret));
  }

  /**
   * Tests that verification returns FALSE when credentials are not configured.
   */
  public function testVerifyWebhookReturnsFalseWithEmptyKeys(): void {
    $client = $this->makeEmptyKeysClient();
    $request = $this->makeRequest('body', 'salt', (string) time(), 'sig');
    $this->assertFalse($client->verifyWebhook($request));
  }

  /**
   * Tests that a correctly signed and fresh webhook is accepted.
   */
  public function testVerifyWebhookValidRequest(): void {
    $access    = 'my_access';
    $secret    = 'my_secret';
    $body      = '{"type":"PAYMENT_COMPLETED"}';
    $salt      = 'abcdef12';
    $timestamp = (string) time();
    $path      = '/gift-card/webhook';

    $signature = $this->sign($path, $salt, $timestamp, $access, $secret, $body);
    $request   = $this->makeRequest($body, $salt, $timestamp, $signature, $path);

    $client = $this->makeClient($access, $secret);
    $this->assertTrue($client->verifyWebhook($request));
  }

  /**
   * Tests that a tampered signature is rejected.
   */
  public function testVerifyWebhookInvalidSignature(): void {
    $client  = $this->makeClient('acc', 'sec');
    $request = $this->makeRequest('body', 'salt', (string) time(), 'wrong_sig');
    $this->assertFalse($client->verifyWebhook($request));
  }

  /**
   * Tests that a webhook with a timestamp older than 5 minutes is rejected.
   */
  public function testVerifyWebhookStaleTimestamp(): void {
    $access    = 'my_access';
    $secret    = 'my_secret';
    $body      = '{}';
    $salt      = 'freshsalt';
    $timestamp = (string) (time() - 400);

    $signature = $this->sign('/gift-card/webhook', $salt, $timestamp, $access, $secret, $body);
    $request   = $this->makeRequest($body, $salt, $timestamp, $signature);

    $client = $this->makeClient($access, $secret);
    $this->assertFalse($client->verifyWebhook($request));
  }

  /**
   * Tests that a previously seen idempotency key is rejected.
   */
  public function testVerifyWebhookDuplicateSalt(): void {
    $access    = 'my_access';
    $secret    = 'my_secret';
    $body      = '{}';
    $salt      = 'seensalt';
    $timestamp = (string) time();

    $signature = $this->sign('/gift-card/webhook', $salt, $timestamp, $access, $secret, $body);
    $request   = $this->makeRequest($body, $salt, $timestamp, $signature);

    $saltStore = $this->createMock(KeyValueStoreExpirableInterface::class);
    $saltStore->method('get')->willReturn(TRUE);

    $client = $this->makeClient($access, $secret, $saltStore);
    $this->assertFalse($client->verifyWebhook($request));
  }

  /**
   * Tests that non-completed event types return NULL from extractCompletedPaymentId.
   */
  public function testExtractCompletedPaymentIdReturnsNullForOtherEvents(): void {
    $client  = $this->makeClient();
    $request = Request::create('/gift-card/webhook', 'POST', [], [], [], [], '{"type":"PAYMENT_PENDING"}');
    $this->assertNull($client->extractCompletedPaymentId($request));
  }

  /**
   * Tests that a completed-payment event returns the payment ID.
   */
  public function testExtractCompletedPaymentIdReturnsIdForCompletedEvent(): void {
    $client  = $this->makeClient();
    $payload = json_encode(['type' => 'PAYMENT_COMPLETED', 'data' => ['id' => 'pay_abc123']]);
    $request = Request::create('/gift-card/webhook', 'POST', [], [], [], [], $payload);
    $this->assertSame('pay_abc123', $client->extractCompletedPaymentId($request));
  }

}
