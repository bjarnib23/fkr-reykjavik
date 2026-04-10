<?php

namespace Drupal\commerce_valitor\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_payment\CreditCard;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_price\Price;

/**
 * Provides the VALITOR payment gateway.
 *
 * @CommercePaymentGateway(
 *   id = "commerce_valitor_mock",
 *   label = "VALITOR MOCK (On-site)",
 *   display_label = "Pay with credit card - valitor mock",
 *   forms = {
 *     "edit-payment-method" = "Drupal\commerce_valitor\PluginForm\PaymentMethodEditForm",
 *     "refund-payment" = "Drupal\commerce_valitor\PluginForm\PaymentRefundForm"
 *   },
 *   payment_method_types = {"credit_card"},
 *   credit_card_types = {
 *     "amex",
 *     "dinersclub",
 *     "discover",
 *     "jcb",
 *     "mastercard",
 *     "visa",
 *   },
 * )
 */
class ValitorMock extends Valitor {

  /**
   * {@inheritdoc}
   */
  public function createPaymentMethod(PaymentMethodInterface $payment_method, array $payment_details) {
    $payment_method->card_type = $payment_details['type'];
    $payment_method->card_number = substr($payment_details['number'], -4);
    $payment_method->card_exp_month = $payment_details['expiration']['month'];
    $payment_method->card_exp_year = $payment_details['expiration']['year'];
    $expires = CreditCard::calculateExpirationTimestamp($payment_details['expiration']['month'], $payment_details['expiration']['year']);
    $payment_method->setRemoteId('mock-' . $payment_details['number']);
    $payment_method->setExpiresTime($expires);
    $payment_method->save();
  }

  /**
   * {@inheritdoc}
   */
  public function createPayment(PaymentInterface $payment, $capture = TRUE) {
    $this->assertPaymentState($payment, ['new']);
    $payment_method = $payment->getPaymentMethod();
    $this->assertPaymentMethod($payment_method);
    $payment_method->setRemoteId('12345');
    $payment->setState('completed');
    $payment->setRemoteId('123456789012|123456|123456');
    $payment->setAuthorizedTime($this->time->getRequestTime());
    $payment->save();
  }

  /**
   * {@inheritdoc}
   */
  public function refundPayment(PaymentInterface $payment, ?Price $amount = NULL) {
    $this->assertPaymentState($payment, ['completed', 'partially_refunded']);
    $amount = $amount ?: $payment->getAmount();
    $old_refunded = $payment->getRefundedAmount();
    $new_refunded = $old_refunded ? $old_refunded->add($amount) : $amount;
    $state = $new_refunded->lessThan($payment->getAmount()) ? 'partially_refunded' : 'refunded';
    $payment->setRefundedAmount($new_refunded);
    $payment->setState($state);
    $payment->save();
  }

}
