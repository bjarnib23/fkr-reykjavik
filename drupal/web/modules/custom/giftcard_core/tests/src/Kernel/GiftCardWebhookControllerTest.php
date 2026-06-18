<?php

namespace Drupal\Tests\giftcard_core\Kernel;

use Drupal\giftcard_core\Controller\GiftCardWebhookController;
use Drupal\giftcard_core\PaymentClientInterface;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests GiftCardWebhookController::receive() across multiple scenarios.
 *
 * @group giftcard_core
 */
#[RunTestsInSeparateProcesses]
class GiftCardWebhookControllerTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['giftcard_core', 'options'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('gift_card');
    $this->installConfig(['giftcard_core']);

    $this->config('system.mail')
      ->set('interface.default', 'test_mail_collector')
      ->save();

    $this->config('system.site')
      ->set('name', 'Test Site')
      ->set('langcode', 'en')
      ->save();
  }

  /**
   * Builds a stub PaymentClientInterface with controllable return values.
   *
   * @param bool $valid
   *   Return value of verifyWebhook().
   * @param string|null $paymentId
   *   Return value of extractCompletedPaymentId().
   *
   * @return \Drupal\giftcard_core\PaymentClientInterface
   *   The configured stub.
   */
  private function makePaymentClient(bool $valid, ?string $paymentId): PaymentClientInterface {
    return new class($valid, $paymentId) implements PaymentClientInterface {

      /**
       * Constructs the stub.
       *
       * @param bool $valid
       *   Whether verifyWebhook() should return TRUE.
       * @param string|null $paymentId
       *   Value that extractCompletedPaymentId() should return.
       */
      public function __construct(
        private readonly bool $valid,
        private readonly ?string $paymentId,
      ) {}

      /**
       * {@inheritdoc}
       */
      public function createCheckout(int $amount, string $currency, string $completeUrl, string $cancelUrl): ?array {
        return NULL;
      }

      /**
       * {@inheritdoc}
       */
      public function verifyWebhook(Request $request): bool {
        return $this->valid;
      }

      /**
       * {@inheritdoc}
       */
      public function extractCompletedPaymentId(Request $request): ?string {
        return $this->paymentId;
      }

    };
  }

  /**
   * Tests that an invalid webhook signature returns HTTP 403.
   */
  public function testInvalidSignatureReturns403(): void {
    $this->container->set('giftcard_core.payment_client', $this->makePaymentClient(FALSE, NULL));
    $controller = GiftCardWebhookController::create($this->container);

    $response = $controller->receive(Request::create('/gift-card/webhook', 'POST'));

    $this->assertSame(403, $response->getStatusCode());
  }

  /**
   * Tests that a valid but non-payment-completed event returns 200 with no entity created.
   */
  public function testUnknownEventReturns200WithNoEntityCreated(): void {
    $this->container->set('giftcard_core.payment_client', $this->makePaymentClient(TRUE, NULL));
    $controller = GiftCardWebhookController::create($this->container);

    $response = $controller->receive(Request::create('/gift-card/webhook', 'POST'));

    $this->assertSame(200, $response->getStatusCode());

    $count = (int) $this->container->get('entity_type.manager')
      ->getStorage('gift_card')
      ->getQuery()
      ->accessCheck(FALSE)
      ->count()
      ->execute();
    $this->assertSame(0, $count);
  }

  /**
   * Tests happy path: entity created and both sender and recipient receive a mail.
   */
  public function testHappyPathCreatesEntityAndSendsTwoMails(): void {
    $paymentId = 'pay_webhook_kernel_001';

    /** @var \Drupal\giftcard_core\GiftCardService $service */
    $service = $this->container->get('giftcard_core.gift_card_service');
    $service->storeCheckoutDataByPaymentId($paymentId, [
      'code'            => 'WBHKERNELTEST001',
      'sender_name'     => 'Alice',
      'sender_email'    => 'alice@example.com',
      'recipient_name'  => 'Bob',
      'recipient_email' => 'bob@example.com',
      'amount'          => 5000,
      'currency'        => 'ISK',
      'message'         => 'Til hamingju!',
      'payment_id'      => $paymentId,
    ]);

    $this->container->set('giftcard_core.payment_client', $this->makePaymentClient(TRUE, $paymentId));
    $controller = GiftCardWebhookController::create($this->container);
    $response = $controller->receive(Request::create('/gift-card/webhook', 'POST'));

    $this->assertSame(200, $response->getStatusCode());

    $entities = $this->container->get('entity_type.manager')
      ->getStorage('gift_card')
      ->loadByProperties(['code' => 'WBHKERNELTEST001']);
    $this->assertCount(1, $entities);
    $this->assertSame('active', reset($entities)->getStatus());

    $mails = \Drupal::state()->get('system.test_mail_collector') ?? [];
    $this->assertCount(2, $mails);
    $this->assertSame('alice@example.com', $mails[0]['to']);
    $this->assertSame('bob@example.com', $mails[1]['to']);
  }

  /**
   * Tests that a second webhook for the same payment returns 200 without a duplicate entity.
   *
   * The first delivery deletes the checkout data; the second finds nothing
   * and exits cleanly without creating a second GiftCard.
   */
  public function testSecondWebhookForSamePaymentDoesNotDuplicate(): void {
    $paymentId = 'pay_dup_kernel_001';

    /** @var \Drupal\giftcard_core\GiftCardService $service */
    $service = $this->container->get('giftcard_core.gift_card_service');
    $service->storeCheckoutDataByPaymentId($paymentId, [
      'code'            => 'DUPKERNELTEST001',
      'sender_name'     => 'Alice',
      'sender_email'    => 'alice@example.com',
      'recipient_name'  => 'Bob',
      'recipient_email' => 'bob@example.com',
      'amount'          => 3000,
      'currency'        => 'ISK',
      'message'         => '',
      'payment_id'      => $paymentId,
    ]);

    $this->container->set('giftcard_core.payment_client', $this->makePaymentClient(TRUE, $paymentId));
    $controller = GiftCardWebhookController::create($this->container);
    $request = Request::create('/gift-card/webhook', 'POST');

    $controller->receive($request);

    $response = $controller->receive($request);
    $this->assertSame(200, $response->getStatusCode());

    $entities = $this->container->get('entity_type.manager')
      ->getStorage('gift_card')
      ->loadByProperties(['code' => 'DUPKERNELTEST001']);
    $this->assertCount(1, $entities);
  }

}
