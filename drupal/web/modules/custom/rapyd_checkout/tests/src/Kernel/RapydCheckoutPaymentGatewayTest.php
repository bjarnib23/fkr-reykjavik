<?php

namespace Drupal\Tests\rapyd_checkout\Kernel;

use Drupal\Tests\commerce_order\Kernel\OrderKernelTestBase;
use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_payment\Entity\PaymentGateway;
use Drupal\commerce_price\Price;
use Drupal\Core\Form\FormState;
use Drupal\Core\Url;
use Drupal\key\Entity\Key;
use Drupal\rapyd_checkout\Plugin\Commerce\PaymentGateway\RapydCheckout;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;

/**
 * Kernel tests for the RapydCheckout payment gateway plugin.
 *
 * Covers onReturn() and onNotify() by injecting a Guzzle MockHandler so no
 * real HTTP calls are made, and using Key entities with known test values so
 * HMAC signatures can be computed in the test assertions.
 *
 * @group rapyd_checkout
 */
#[CoversClass(RapydCheckout::class)]
#[Group('rapyd_checkout')]
#[RunTestsInSeparateProcesses]
class RapydCheckoutPaymentGatewayTest extends OrderKernelTestBase {

  /**
   * Modules to enable.
   *
   * @var string[]
   */
  protected static $modules = [
    'commerce_payment',
    'key',
    'rapyd_checkout',
  ];

  /**
   * Known test access key value.
   */
  private const ACCESS = 'test_rapyd_access';

  /**
   * Known test secret key value.
   */
  private const SECRET = 'test_rapyd_secret';

  /**
   * Returns the Commerce-generated webhook notify path for the test gateway.
   *
   * Using the actual route generator (rather than a hardcoded string) ensures
   * the test exercises the real production path if the route definition
   * changes.
   */
  private function notifyPath(): string {
    return Url::fromRoute('commerce_payment.notify', [
      'commerce_payment_gateway' => 'rapyd',
    ])->toString();
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('commerce_payment');
    $this->installConfig('commerce_payment');

    Key::create([
      'id' => 'test_access',
      'label' => 'Test Rapyd access key',
      'key_provider' => 'config',
      'key_provider_settings' => ['key_value' => self::ACCESS],
    ])->save();

    Key::create([
      'id' => 'test_secret',
      'label' => 'Test Rapyd secret key',
      'key_provider' => 'config',
      'key_provider_settings' => ['key_value' => self::SECRET],
    ])->save();

    PaymentGateway::create([
      'id' => 'rapyd',
      'label' => 'Rapyd',
      'plugin' => 'rapyd_checkout',
      'mode' => 'test',
      'configuration' => [
        'access_key_id' => 'test_access',
        'secret_key_id' => 'test_secret',
        'currency' => 'USD',
        'country' => 'US',
      ],
    ])->save();
  }

  /**
   * Creates a Commerce order with one line item and the Rapyd gateway set.
   */
  private function createTestOrder(): Order {
    $order_item = OrderItem::create([
      'type' => 'test',
      'quantity' => 1,
      'unit_price' => new Price('100', 'USD'),
    ]);
    $order_item->save();

    $order = Order::create([
      'type' => 'default',
      'store_id' => $this->store->id(),
      'order_items' => [$order_item],
      'payment_gateway' => 'rapyd',
      'mail' => 'customer@example.com',
    ]);
    $order->save();
    return $order;
  }

  /**
   * Loads the gateway plugin, replacing the HTTP client with a mock.
   *
   * @param \GuzzleHttp\Psr7\Response[] $mock_responses
   *   Guzzle responses to return in sequence.
   */
  private function loadPlugin(array $mock_responses = []): RapydCheckout {
    if ($mock_responses) {
      $stack = HandlerStack::create(new MockHandler($mock_responses));
      $this->container->set('http_client', new Client(['handler' => $stack]));
    }
    /** @var \Drupal\rapyd_checkout\Plugin\Commerce\PaymentGateway\RapydCheckout */
    return PaymentGateway::load('rapyd')->getPlugin();
  }

  /**
   * Computes a valid Rapyd HMAC signature for an inbound webhook request.
   *
   * @param string $body
   *   Raw JSON body.
   * @param string $salt
   *   Value used as rapyd-idempotency header.
   * @param string $timestamp
   *   Value used as rapyd-timestamp header.
   * @param string $path
   *   Request path info.
   *
   * @return string
   *   Base64-encoded HMAC-SHA256 signature.
   */
  private function sign(
    string $body,
    string $salt,
    string $timestamp,
    string $path,
  ): string {
    $to_sign = 'post' . $path . $salt . $timestamp
      . self::ACCESS . self::SECRET . $body;
    return base64_encode(hash_hmac('sha256', $to_sign, self::SECRET));
  }

  /**
   * Tests that onReturn() creates a payment and places the order.
   */
  public function testOnReturnCreatesPaymentAndPlacesOrder(): void {
    $order = $this->createTestOrder();
    $order->setData('rapyd_checkout_id', 'checkout_test_abc');
    $order->save();

    $plugin = $this->loadPlugin([
      new Response(200, [], json_encode([
        'data' => [
          'status' => 'DON',
          'payment' => ['id' => 'pay_test_001', 'status' => 'CLO', 'amount' => 10000],
        ],
      ])),
    ]);

    $request = Request::create('/checkout/' . $order->id() . '/payment/return');
    $plugin->onReturn($order, $request);

    $payment_storage = $this->container->get('entity_type.manager')
      ->getStorage('commerce_payment');
    $payments = $payment_storage->loadByProperties(['order_id' => $order->id()]);

    $this->assertCount(1, $payments, 'Exactly one payment entity was created.');
    $payment = reset($payments);
    $this->assertEquals('completed', $payment->getState()->getId());
    $this->assertEquals('pay_test_001', $payment->getRemoteId());

    $order = $this->reloadEntity($order);
    $this->assertEquals(
      'completed',
      $order->getState()->getId(),
      'Order was placed after successful return.'
    );
  }

  /**
   * Tests that onReturn() is idempotent — a second call creates no duplicate.
   */
  public function testOnReturnIsIdempotent(): void {
    $order = $this->createTestOrder();
    $order->setData('rapyd_checkout_id', 'checkout_test_idem');
    $order->save();

    $checkout_data = json_encode([
      'data' => [
        'status' => 'DON',
        'payment' => ['id' => 'pay_test_idem', 'status' => 'CLO', 'amount' => 10000],
      ],
    ]);

    // Two identical HTTP responses — one per onReturn() call.
    $plugin = $this->loadPlugin([
      new Response(200, [], $checkout_data),
      new Response(200, [], $checkout_data),
    ]);

    $request = Request::create('/checkout/' . $order->id() . '/payment/return');
    $plugin->onReturn($order, $request);
    $plugin->onReturn($order, $request);

    $payment_storage = $this->container->get('entity_type.manager')
      ->getStorage('commerce_payment');
    $payments = $payment_storage->loadByProperties(['order_id' => $order->id()]);

    $this->assertCount(
      1,
      $payments,
      'Duplicate onReturn() calls do not create a second payment.'
    );
  }

  /**
   * Tests that onReturn() after onNotify() does not throw or double-place.
   *
   * Validates the §1.4 order-state guard: when the webhook arrives first and
   * places the order, a subsequent onReturn() must not call applyTransitionById
   * on an already-completed order.
   */
  public function testOnReturnAfterOnNotifyDoesNotThrow(): void {
    $order = $this->createTestOrder();

    // Send a webhook that places the order first.
    $salt = 'salt_state_guard_01';
    $timestamp = (string) time();
    $body = json_encode([
      'type' => 'PAYMENT_COMPLETED',
      'data' => [
        'id' => 'pay_state_guard',
        'merchant_reference_id' => 'rapyd-order-' . $order->id(),
      ],
    ]);
    $signature = $this->sign($body, $salt, $timestamp, $this->notifyPath());

    $request = Request::create($this->notifyPath(), 'POST', [], [], [], [], $body);
    $request->headers->set('rapyd-idempotency', $salt);
    $request->headers->set('rapyd-timestamp', $timestamp);
    $request->headers->set('rapyd-signature', $signature);

    $plugin = $this->loadPlugin();
    $plugin->onNotify($request);

    $order = $this->reloadEntity($order);
    $this->assertEquals('completed', $order->getState()->getId());

    // Now simulate onReturn() arriving after the webhook placed the order.
    $order->setData('rapyd_checkout_id', 'checkout_state_guard');
    $order->save();

    $plugin = $this->loadPlugin([
      new Response(200, [], json_encode([
        'data' => [
          'status' => 'DON',
          'payment' => ['id' => 'pay_state_guard', 'status' => 'CLO', 'amount' => 10000],
        ],
      ])),
    ]);

    $return_request = Request::create(
      '/checkout/' . $order->id() . '/payment/return'
    );
    // Must not throw even though the order is already completed.
    $plugin->onReturn($order, $return_request);

    $payment_storage = $this->container->get('entity_type.manager')
      ->getStorage('commerce_payment');
    $payments = $payment_storage->loadByProperties(['order_id' => $order->id()]);
    $this->assertCount(1, $payments, 'Only one payment created across webhook + return.');
  }

  /**
   * Tests that onNotify() creates a payment and places the order on a webhook.
   */
  public function testOnNotifyCreatesPaymentAndPlacesOrder(): void {
    $order = $this->createTestOrder();

    $salt = 'testsalt00000001';
    $timestamp = (string) time();
    $body = json_encode([
      'type' => 'PAYMENT_COMPLETED',
      'data' => [
        'id' => 'pay_notify_001',
        'merchant_reference_id' => 'rapyd-order-' . $order->id(),
      ],
    ]);
    $signature = $this->sign($body, $salt, $timestamp, $this->notifyPath());

    $request = Request::create($this->notifyPath(), 'POST', [], [], [], [], $body);
    $request->headers->set('rapyd-idempotency', $salt);
    $request->headers->set('rapyd-timestamp', $timestamp);
    $request->headers->set('rapyd-signature', $signature);

    $plugin = $this->loadPlugin();
    $response = $plugin->onNotify($request);

    $this->assertEquals(200, $response->getStatusCode());

    $payment_storage = $this->container->get('entity_type.manager')
      ->getStorage('commerce_payment');
    $payments = $payment_storage->loadByProperties(['order_id' => $order->id()]);

    $this->assertCount(1, $payments, 'Payment was created from webhook.');
    $payment = reset($payments);
    $this->assertEquals('completed', $payment->getState()->getId());
    $this->assertEquals('pay_notify_001', $payment->getRemoteId());

    $order = $this->reloadEntity($order);
    $this->assertEquals(
      'completed',
      $order->getState()->getId(),
      'Order was placed after webhook.'
    );
  }

  /**
   * Tests that onNotify() returns 401 and creates no payment on bad signature.
   */
  public function testOnNotifyRejectsBadSignature(): void {
    $order = $this->createTestOrder();

    $body = json_encode([
      'type' => 'PAYMENT_COMPLETED',
      'data' => [
        'id' => 'pay_bad_sig',
        'merchant_reference_id' => 'rapyd-order-' . $order->id(),
      ],
    ]);

    $request = Request::create($this->notifyPath(), 'POST', [], [], [], [], $body);
    $request->headers->set('rapyd-idempotency', 'salt');
    $request->headers->set('rapyd-timestamp', (string) time());
    $request->headers->set('rapyd-signature', 'completely_wrong_signature');

    $plugin = $this->loadPlugin();
    $response = $plugin->onNotify($request);

    $this->assertEquals(401, $response->getStatusCode());

    $payment_storage = $this->container->get('entity_type.manager')
      ->getStorage('commerce_payment');
    $payments = $payment_storage->loadByProperties(['order_id' => $order->id()]);
    $this->assertCount(0, $payments, 'No payment created when signature is invalid.');
  }

  /**
   * Tests that onNotify() discards webhooks with a stale timestamp.
   *
   * Validates §1.5 timestamp-skew protection: a correctly signed but
   * replayed (old) request must be accepted with 200 and no side effects.
   */
  public function testOnNotifyRejectsStaleTimestamp(): void {
    $order = $this->createTestOrder();

    // Use a timestamp 10 minutes in the past.
    $timestamp = (string) (time() - 600);
    $salt = 'salt_stale_ts_001';
    $body = json_encode([
      'type' => 'PAYMENT_COMPLETED',
      'data' => [
        'id' => 'pay_stale',
        'merchant_reference_id' => 'rapyd-order-' . $order->id(),
      ],
    ]);
    $signature = $this->sign($body, $salt, $timestamp, $this->notifyPath());

    $request = Request::create($this->notifyPath(), 'POST', [], [], [], [], $body);
    $request->headers->set('rapyd-idempotency', $salt);
    $request->headers->set('rapyd-timestamp', $timestamp);
    $request->headers->set('rapyd-signature', $signature);

    $plugin = $this->loadPlugin();
    $response = $plugin->onNotify($request);

    // Returns 200 so Rapyd does not retry endlessly.
    $this->assertEquals(200, $response->getStatusCode());

    $payment_storage = $this->container->get('entity_type.manager')
      ->getStorage('commerce_payment');
    $payments = $payment_storage->loadByProperties(['order_id' => $order->id()]);
    $this->assertCount(0, $payments, 'No payment created for stale timestamp.');
  }

  /**
   * Tests that onNotify() is idempotent — duplicates create no extra payment.
   */
  public function testOnNotifyIsIdempotent(): void {
    $order = $this->createTestOrder();

    $salt = 'testsalt00000002';
    $timestamp = (string) time();
    $body = json_encode([
      'type' => 'PAYMENT_COMPLETED',
      'data' => [
        'id' => 'pay_notify_idem',
        'merchant_reference_id' => 'rapyd-order-' . $order->id(),
      ],
    ]);
    $signature = $this->sign($body, $salt, $timestamp, $this->notifyPath());

    $request = Request::create($this->notifyPath(), 'POST', [], [], [], [], $body);
    $request->headers->set('rapyd-idempotency', $salt);
    $request->headers->set('rapyd-timestamp', $timestamp);
    $request->headers->set('rapyd-signature', $signature);

    $plugin = $this->loadPlugin();
    $plugin->onNotify($request);
    $plugin->onNotify($request);

    $payment_storage = $this->container->get('entity_type.manager')
      ->getStorage('commerce_payment');
    $payments = $payment_storage->loadByProperties(['order_id' => $order->id()]);
    $this->assertCount(
      1,
      $payments,
      'Duplicate webhooks do not create duplicate payments.'
    );
  }

  /**
   * Tests that validateConfigurationForm() rejects an unknown currency code.
   */
  public function testValidateConfigurationFormRejectsInvalidCurrency(): void {
    $plugin = $this->loadPlugin();

    $form       = ['#parents' => []];
    $form_state = new FormState();
    $form_state->setValues([
      'access_key_id' => 'test_access',
      'secret_key_id' => 'test_secret',
      'currency'      => 'NOT_REAL',
      'country'       => 'US',
    ]);

    $plugin->validateConfigurationForm($form, $form_state);

    $this->assertArrayHasKey(
      'currency',
      $form_state->getErrors(),
      'An unrecognised ISO 4217 currency code must produce a form error.'
    );
  }

  /**
   * Tests that validateConfigurationForm() rejects a malformed country code.
   */
  public function testValidateConfigurationFormRejectsInvalidCountry(): void {
    $plugin = $this->loadPlugin();

    $form       = ['#parents' => []];
    $form_state = new FormState();
    $form_state->setValues([
      'access_key_id' => 'test_access',
      'secret_key_id' => 'test_secret',
      'currency'      => 'USD',
      'country'       => 'TOOLONG',
    ]);

    $plugin->validateConfigurationForm($form, $form_state);

    $this->assertArrayHasKey(
      'country',
      $form_state->getErrors(),
      'A country code longer than two letters must produce a form error.'
    );
  }

  /**
   * Tests that validateConfigurationForm() accepts a valid configuration.
   */
  public function testValidateConfigurationFormAcceptsValidConfig(): void {
    $plugin = $this->loadPlugin();

    $form       = ['#parents' => []];
    $form_state = new FormState();
    $form_state->setValues([
      'access_key_id' => 'test_access',
      'secret_key_id' => 'test_secret',
      'currency'      => 'USD',
      'country'       => 'US',
    ]);

    $plugin->validateConfigurationForm($form, $form_state);

    $this->assertEmpty(
      $form_state->getErrors(),
      'A valid configuration must not produce any form errors.'
    );
  }

}
