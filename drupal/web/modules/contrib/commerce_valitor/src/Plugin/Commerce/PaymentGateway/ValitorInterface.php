<?php

namespace Drupal\commerce_valitor\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OnsitePaymentGatewayInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsAuthorizationsInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsRefundsInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsUpdatingStoredPaymentMethodsInterface;
use Drupal\commerce_price\Price;

/**
 * Provides the interface for the commerce_valitor payment gateway.
 */
interface ValitorInterface extends OnsitePaymentGatewayInterface, SupportsAuthorizationsInterface, SupportsRefundsInterface, SupportsUpdatingStoredPaymentMethodsInterface {

  /**
   * Verifies card .
   *
   * @param string $cardNumber
   *   Card number to verify.
   * @param int $expirationMonth
   *   Card expiration month.
   * @param int $expirationYear
   *   Card expiration year.
   * @param int $cvc
   *   Card CVC.
   * @param int $order_id
   *   Order ID (optional).
   * @param string $order_total_amount
   *   Order total (optional)
   *
   * @return mixed
   *   The verification result.
   */
  public function verifyCard($cardNumber, $expirationMonth, $expirationYear, $cvc, $order_id = NULL, $order_total_amount = 0);

  /**
   * Get minor units for price.
   *
   * For example, 9.99 USD becomes 999.
   *
   * @param \Drupal\commerce_price\Price $amount
   *   Price object to convert.
   *
   * @return float|int|void
   *   The amount in minor units, at best an integer.
   */
  public function getMinorUnits(Price $amount);

}
