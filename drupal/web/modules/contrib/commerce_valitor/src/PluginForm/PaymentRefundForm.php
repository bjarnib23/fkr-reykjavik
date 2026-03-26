<?php

namespace Drupal\commerce_valitor\PluginForm;

use Drupal\commerce_payment\PluginForm\PaymentGatewayFormBase;
use Drupal\commerce_price\Price;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Messenger\MessengerTrait;

/**
 * Class PaymentRefundForm.
 *
 * Allows to refund Valitor paymetns.
 */
class PaymentRefundForm extends PaymentGatewayFormBase {

  use LoggerChannelTrait;
  use MessengerTrait;

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
    $payment = $this->entity;

    $form['#success_message'] = $this->t('Payment refunded.');
    $form['amount'] = [
      '#type' => 'commerce_price',
      '#title' => $this->t('Amount'),
      '#default_value' => $payment->getBalance()->toArray(),
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValue($form['#parents']);
    $amount = new Price($values['amount']['number'], $values['amount']['currency_code']);
    /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
    $payment = $this->entity;
    $balance = $payment->getBalance();
    if ($amount->greaterThan($balance)) {
      $form_state->setError($form['amount'], $this->t("Can't refund more than @amount.", ['@amount' => $balance->__toString()]));
    }

    // If the transaction is older than 120 days, display an error message and
    // redirect.
    if (\Drupal::time()->getRequestTime() - $payment->getAuthorizedTime() > 86400 * 120) {
      $form_state->setError($form['amount'], $this->t('This capture has passed its 120 day limit for issuing credits.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValue($form['#parents']);
    $amount = new Price($values['amount']['number'], $values['amount']['currency_code']);
    /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
    $payment = $this->entity;
    /** @var \Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsRefundsInterface $payment_gateway_plugin */
    $payment_gateway_plugin = $this->plugin;
    try {
      $payment_gateway_plugin->refundPayment($payment, $amount);
    }
    catch (\Exception $e) {
      $this->getLogger('commerce_valitor')->error('Refund request failed with following message: "@message"', [
        '@message', $e->getMessage(),
      ]);
      $this->messenger()->addError($this->t('Refund request failed with following message: "@message"', [
        '@message' => $e->getMessage(),
      ]));
    }

  }

}
