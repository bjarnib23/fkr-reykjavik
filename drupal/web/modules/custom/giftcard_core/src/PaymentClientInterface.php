<?php

namespace Drupal\giftcard_core;

use Symfony\Component\HttpFoundation\Request;

/**
 * Interface for payment gateway clients.
 *
 * Implement this interface to add support for any payment provider
 * (e.g. Rapyd, Stripe, PayPal) without modifying the gift card module itself.
 * Each implementation owns its own webhook headers, event-type names, and
 * credential config — none of these leak into the interface contract.
 */
interface PaymentClientInterface {

  /**
   * Creates a hosted checkout session and returns the redirect URL.
   *
   * @param int    $amount      Amount in the major currency unit (e.g. 5000 ISK).
   * @param string $currency    ISO 4217 currency code.
   * @param string $completeUrl Absolute URL to redirect to after payment.
   * @param string $cancelUrl   Absolute URL to redirect to on cancellation.
   *
   * @return array{redirect_url: string, payment_id: string}|null
   *   Array with redirect URL and payment ID, or NULL on failure.
   */
  public function createCheckout(int $amount, string $currency, string $completeUrl, string $cancelUrl): ?array;

  /**
   * Verifies the authenticity of an inbound webhook request.
   *
   * Implementations are responsible for extracting provider-specific
   * headers, verifying the signature, enforcing timestamp-skew limits,
   * and guarding against replay attacks.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The full inbound HTTP request.
   *
   * @return bool
   *   TRUE if the request is authentic, FALSE otherwise.
   */
  public function verifyWebhook(Request $request): bool;

  /**
   * Extracts the payment ID from a completed-payment event.
   *
   * Called only after verifyWebhook() returns TRUE. Returns NULL if
   * the event type is not a completed payment (the caller should
   * acknowledge the event with 200 but take no action).
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The full inbound HTTP request.
   *
   * @return string|null
   *   The payment ID, or NULL if this is not a completed-payment event.
   */
  public function extractCompletedPaymentId(Request $request): ?string;

}
