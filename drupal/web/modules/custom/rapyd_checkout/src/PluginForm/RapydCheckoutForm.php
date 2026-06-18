<?php

namespace Drupal\rapyd_checkout\PluginForm;

use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\PluginForm\PaymentOffsiteForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Initiates a Rapyd Hosted Checkout session and redirects the customer.
 */
class RapydCheckoutForm extends PaymentOffsiteForm {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);

    /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
    $payment = $this->entity;
    $order = $payment->getOrder();

    /** @var \Drupal\rapyd_checkout\Plugin\Commerce\PaymentGateway\RapydCheckout $gateway */
    $gateway = $payment->getPaymentGateway()->getPlugin();
    $gateway_config = $gateway->getConfiguration();

    // Reject orders whose currency does not match the configured gateway
    // currency.
    $order_currency = $order->getTotalPrice()->getCurrencyCode();
    $gateway_currency = $gateway_config['currency'] ?? '';
    if ($gateway_currency && $order_currency !== $gateway_currency) {
      throw new PaymentGatewayException(sprintf(
        'Order currency %s does not match gateway currency %s.',
        $order_currency,
        $gateway_currency,
      ));
    }

    // Reuse an existing Rapyd session so that pressing Back and continuing
    // does not orphan the first checkout.
    $checkout_id = $order->getData('rapyd_checkout_id');
    $redirect_url = $order->getData('rapyd_checkout_redirect_url');

    if (!$checkout_id || !$redirect_url) {
      $return_url = Url::fromRoute('commerce_payment.checkout.return', [
        'commerce_order' => $order->id(),
        'step' => 'payment',
      ], ['absolute' => TRUE])->toString();

      $cancel_url = Url::fromRoute('commerce_payment.checkout.cancel', [
        'commerce_order' => $order->id(),
        'step' => 'payment',
      ], ['absolute' => TRUE])->toString();

      $client = $gateway->getRapydClient();
      try {
        $result = $client->createCheckout(
          (int) $order->id(),
          $gateway->toMinorUnits($order->getTotalPrice()),
          $gateway_config['currency'],
          $gateway_config['country'],
          $return_url,
          $cancel_url,
        );
      }
      catch (\RuntimeException $e) {
        throw new PaymentGatewayException(
          'Could not initiate Rapyd checkout: ' . $e->getMessage(), 0, $e
        );
      }

      $order->setData('rapyd_checkout_id', $result['checkout_id']);
      $order->setData('rapyd_checkout_redirect_url', $result['redirect_url']);
      $order->save();

      $redirect_url = $result['redirect_url'];
    }

    return $this->buildRedirectForm($form, $form_state, $redirect_url, [], self::REDIRECT_GET);
  }

}
