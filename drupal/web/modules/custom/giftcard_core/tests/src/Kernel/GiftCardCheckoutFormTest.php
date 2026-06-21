<?php

namespace Drupal\Tests\giftcard_core\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\giftcard_core\Form\GiftCardCheckoutForm;
use Drupal\giftcard_core\PaymentClientInterface;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Tests GiftCardCheckoutForm submission, checkout data storage, and flood control.
 *
 * @group giftcard_core
 */
#[RunTestsInSeparateProcesses]
class GiftCardCheckoutFormTest extends EntityKernelTestBase {

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
    $this->container->set('flood', new \Drupal\Core\Flood\MemoryBackend($this->container->get('request_stack')));

    $this->config('giftcard_core.settings')
      ->set('currency', 'ISK')
      ->set('min_amount', 1000)
      ->set('flood_threshold', 5)
      ->save();

    $paymentClient = new class implements PaymentClientInterface {

      /**
       * {@inheritdoc}
       */
      public function createCheckout(int $amount, string $currency, string $completeUrl, string $cancelUrl): ?array {
        return [
          'redirect_url' => 'https://sandboxapi.rapyd.net/checkout/test_form_123',
          'payment_id'   => 'pay_form_test_001',
        ];
      }

      /**
       * {@inheritdoc}
       */
      public function verifyWebhook(Request $request): bool {
        return FALSE;
      }

      /**
       * {@inheritdoc}
       */
      public function extractCompletedPaymentId(Request $request): ?string {
        return NULL;
      }

    };
    $this->container->set('giftcard_core.payment_client', $paymentClient);

    // Push a request with a session so PrivateTempStore does not throw
    // SessionNotFoundException during form submission.
    $request = Request::create('/gift-card/buy', 'POST');
    $request->server->set('REMOTE_ADDR', '127.0.0.1');
    $request->setSession(new Session(new MockArraySessionStorage()));
    $this->container->get('request_stack')->push($request);
  }

  /**
   * Returns a valid set of form values for a gift card purchase.
   *
   * @return array
   *   Form values keyed by element name.
   */
  private function validValues(): array {
    return [
      'sender_name'     => 'Alice',
      'sender_email'    => 'alice@example.com',
      'recipient_name'  => 'Bob',
      'recipient_email' => 'bob@example.com',
      'message'         => 'Til hamingju með afmælið!',
      'amount'          => 5000,
    ];
  }

  /**
   * Tests that a valid submission stores checkout data and sets an external redirect.
   */
  public function testValidSubmitStoresCheckoutDataAndRedirects(): void {
    $form_state = new FormState();
    $form_state->setValues($this->validValues());

    \Drupal::formBuilder()->submitForm(GiftCardCheckoutForm::class, $form_state);

    $this->assertFalse(
      $form_state->hasAnyErrors(),
      'Form should have no errors on valid submit: ' . implode(', ', array_keys($form_state->getErrors()))
    );

    /** @var \Drupal\giftcard_core\GiftCardService $service */
    $service = $this->container->get('giftcard_core.gift_card_service');

    // Checkout data keyed by payment ID must be stored for the webhook to find.
    $storedByPayment = $service->getCheckoutDataByPaymentId('pay_form_test_001');
    $this->assertNotNull($storedByPayment, 'Checkout data should be stored under the payment ID.');
    $this->assertSame('Alice', $storedByPayment['sender_name']);
    $this->assertSame('Bob', $storedByPayment['recipient_name']);
    $this->assertSame('pay_form_test_001', $storedByPayment['payment_id']);

    // Form must redirect to the payment provider's hosted page.
    $response = $form_state->getResponse();
    $this->assertNotNull($response, 'Form should set a redirect response after successful submit.');
    $this->assertStringStartsWith('https://', $response->getTargetUrl());
  }

  /**
   * Tests that flood control blocks submission after the configured threshold.
   */
  public function testFloodBlocksSubmitAfterThreshold(): void {
    $this->config('giftcard_core.settings')
      ->set('flood_threshold', 1)
      ->save();

    // First submit: within threshold, should pass validation.
    $form_state1 = new FormState();
    $form_state1->setValues($this->validValues());
    \Drupal::formBuilder()->submitForm(GiftCardCheckoutForm::class, $form_state1);
    $this->assertFalse($form_state1->hasAnyErrors(), 'First submit should pass within threshold.');

    // Second submit: threshold exceeded, validation must fail.
    $form_state2 = new FormState();
    $form_state2->setValues($this->validValues());
    \Drupal::formBuilder()->submitForm(GiftCardCheckoutForm::class, $form_state2);
    $this->assertTrue($form_state2->hasAnyErrors(), 'Second submit should be blocked by flood control.');
  }

}
