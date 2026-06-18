<?php

namespace Drupal\giftcard_core;

use Symfony\Component\HttpFoundation\Request;

/**
 * No-op payment client used as a fallback when no gateway module is installed.
 *
 * Install a gateway sub-module (e.g. giftcard_rapyd) to replace this service
 * with a real implementation.
 */
class NullPaymentClient implements PaymentClientInterface {

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
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function extractCompletedPaymentId(Request $request): ?string {
    return NULL;
  }

}
