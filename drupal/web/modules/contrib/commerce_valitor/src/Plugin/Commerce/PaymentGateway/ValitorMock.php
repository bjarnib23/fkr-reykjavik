<?php

namespace Drupal\commerce_valitor\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_payment\Entity\PaymentInterface;

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

}
