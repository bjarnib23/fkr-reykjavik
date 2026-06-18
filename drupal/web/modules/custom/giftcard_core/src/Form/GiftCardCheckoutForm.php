<?php

namespace Drupal\giftcard_core\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Url;
use Drupal\giftcard_core\GiftCardService;
use Drupal\giftcard_core\PaymentClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Public gift card purchase form.
 *
 * Stores checkout data in PrivateTempStore (for the thank-you page) and in
 * the expirable key-value store keyed by payment ID (for webhook lookup).
 * Redirects the buyer to the payment provider's hosted checkout page.
 */
class GiftCardCheckoutForm extends FormBase {

  private const FLOOD_EVENT = 'giftcard_core_checkout';

  /**
   * Constructs a new GiftCardCheckoutForm.
   *
   * @param \Drupal\giftcard_core\GiftCardService $giftCardService
   *   The gift card service.
   * @param \Drupal\giftcard_core\PaymentClientInterface $paymentClient
   *   The payment client.
   * @param \Drupal\Core\Flood\FloodInterface $flood
   *   The flood service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   */
  public function __construct(
    private readonly GiftCardService $giftCardService,
    private readonly PaymentClientInterface $paymentClient,
    private readonly FloodInterface $flood,
    ConfigFactoryInterface $configFactory,
  ) {
    $this->setConfigFactory($configFactory);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new self(
      $container->get('giftcard_core.gift_card_service'),
      $container->get('giftcard_core.payment_client'),
      $container->get('flood'),
      $container->get('config.factory'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'giftcard_core_checkout_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config    = $this->configFactory()->get('giftcard_core.settings');
    $currency  = $config->get('currency');
    $minAmount = (int) $config->get('min_amount');

    $form['sender'] = [
      '#type'  => 'fieldset',
      '#title' => $this->t('Your details'),
    ];

    $form['sender']['sender_name'] = [
      '#type'      => 'textfield',
      '#title'     => $this->t('Your name'),
      '#required'  => TRUE,
      '#maxlength' => 255,
    ];

    $form['sender']['sender_email'] = [
      '#type'     => 'email',
      '#title'    => $this->t('Your email'),
      '#required' => TRUE,
    ];

    $form['recipient'] = [
      '#type'  => 'fieldset',
      '#title' => $this->t('Recipient details'),
    ];

    $form['recipient']['recipient_name'] = [
      '#type'      => 'textfield',
      '#title'     => $this->t('Recipient name'),
      '#required'  => TRUE,
      '#maxlength' => 255,
    ];

    $form['recipient']['recipient_email'] = [
      '#type'     => 'email',
      '#title'    => $this->t('Recipient email'),
      '#required' => TRUE,
    ];

    $form['recipient']['message'] = [
      '#type'  => 'textarea',
      '#title' => $this->t('Personal message'),
      '#rows'  => 4,
    ];

    $form['amount'] = [
      '#type'     => 'number',
      '#title'    => $this->t('Amount (@currency)', ['@currency' => $currency]),
      '#required' => TRUE,
      '#min'      => $minAmount,
      '#step'     => 1,
    ];

    $form['submit'] = [
      '#type'  => 'submit',
      '#value' => $this->t('Proceed to payment'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $config    = $this->configFactory()->get('giftcard_core.settings');
    $threshold = (int) $config->get('flood_threshold');
    $minAmount = (int) $config->get('min_amount');
    $currency  = $config->get('currency');
    $ip        = $this->getRequest()->getClientIp();

    if (!$this->flood->isAllowed(self::FLOOD_EVENT, $threshold, 3600, $ip)) {
      $form_state->setErrorByName('', $this->t('Too many purchase attempts. Please try again later.'));
      return;
    }

    $amount = (int) $form_state->getValue('amount');
    if ($amount < $minAmount) {
      $form_state->setErrorByName('amount', $this->t('The minimum gift card amount is @min @currency.', [
        '@min'      => $minAmount,
        '@currency' => $currency,
      ]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $ip = $this->getRequest()->getClientIp();
    $this->flood->register(self::FLOOD_EVENT, 3600, $ip);

    $config   = $this->configFactory()->get('giftcard_core.settings');
    $currency = $config->get('currency');

    $checkoutData = [
      'sender_name'     => $form_state->getValue('sender_name'),
      'sender_email'    => $form_state->getValue('sender_email'),
      'recipient_name'  => $form_state->getValue('recipient_name'),
      'recipient_email' => $form_state->getValue('recipient_email'),
      'message'         => $form_state->getValue('message') ?? '',
      'amount'          => (int) $form_state->getValue('amount'),
      'currency'        => $currency,
    ];

    $completeUrl = Url::fromRoute('giftcard_core.thank_you', [], ['absolute' => TRUE])->toString();
    $cancelUrl   = Url::fromRoute('giftcard_core.cancel', [], ['absolute' => TRUE])->toString();

    $session = $this->paymentClient->createCheckout(
      $checkoutData['amount'],
      $checkoutData['currency'],
      $completeUrl,
      $cancelUrl,
    );

    if ($session === NULL) {
      $this->getLogger('giftcard_core')->error('Failed to create payment checkout session.');
      $this->messenger()->addError($this->t('The payment gateway is unavailable. Please try again later.'));
      return;
    }

    $checkoutData['payment_id'] = $session['payment_id'];

    $this->giftCardService->storeCheckoutData($checkoutData);
    $this->giftCardService->storeCheckoutDataByPaymentId($session['payment_id'], $checkoutData);

    $form_state->setResponse(new TrustedRedirectResponse($session['redirect_url']));
  }

}
