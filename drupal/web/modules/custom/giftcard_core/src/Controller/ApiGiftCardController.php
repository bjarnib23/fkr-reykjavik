<?php

namespace Drupal\giftcard_core\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Flood\FloodInterface;
use Drupal\giftcard_core\GiftCardService;
use Drupal\giftcard_core\PaymentClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides gift card amounts and checkout endpoints for the React frontend.
 */
class ApiGiftCardController extends ControllerBase {

  private const FLOOD_EVENT = 'giftcard_core_api_checkout';

  /**
   * Constructs an ApiGiftCardController object.
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
    return new static(
      $container->get('giftcard_core.gift_card_service'),
      $container->get('giftcard_core.payment_client'),
      $container->get('flood'),
      $container->get('config.factory'),
    );
  }

  /**
   * Returns the list of predefined gift card amounts.
   *
   * GET /api/fkr/giftcard/amounts
   *
   * Response: [{"sku": "GJAFABREF-5000", "amount": 5000, "label": "5.000 kr"}, ...]
   */
  public function amounts(Request $request): JsonResponse {
    if ($request->getMethod() === 'OPTIONS') {
      return new JsonResponse([], 200, $this->cors());
    }

    $config  = $this->configFactory()->get('giftcard_core.settings');
    $amounts = $config->get('predefined_amounts') ?: [];

    $result = [];
    foreach ($amounts as $amount) {
      $amount    = (int) $amount;
      $result[] = [
        'sku'    => 'GJAFABREF-' . $amount,
        'amount' => $amount,
        'label'  => number_format($amount, 0, '.', '.') . ' kr',
      ];
    }

    return new JsonResponse($result, 200, $this->cors());
  }

  /**
   * Initiates a hosted gift card checkout session.
   *
   * POST /api/fkr/giftcard/checkout
   * Body: {sku, name, email, phone, notes}
   *
   * The SKU encodes the amount (e.g. GJAFABREF-5000 → 5000).
   * The name/email are used as both sender and recipient since the React
   * frontend does not collect separate recipient details.
   *
   * Response: {"checkout_url": "https://..."}
   */
  public function checkout(Request $request): JsonResponse {
    if ($request->getMethod() === 'OPTIONS') {
      return new JsonResponse([], 200, $this->cors());
    }

    $config    = $this->configFactory()->get('giftcard_core.settings');
    $threshold = (int) ($config->get('flood_threshold') ?: 5);
    $ip        = $request->getClientIp();

    if (!$this->flood->isAllowed(self::FLOOD_EVENT, $threshold, 3600, $ip)) {
      return new JsonResponse(
        ['error' => 'Too many purchase attempts. Please try again later.'],
        429,
        $this->cors()
      );
    }

    $data = json_decode($request->getContent(), TRUE) ?? [];

    foreach (['sku', 'name', 'email'] as $field) {
      if (empty($data[$field])) {
        return new JsonResponse(['error' => "Missing required field: $field"], 400, $this->cors());
      }
    }

    // Parse amount from SKU (e.g. GJAFABREF-5000 → 5000).
    if (!preg_match('/^GJAFABREF-(\d+)$/', $data['sku'], $matches)) {
      return new JsonResponse(['error' => 'Invalid gift card amount.'], 400, $this->cors());
    }
    $amount   = (int) $matches[1];
    $currency = $config->get('currency') ?: 'ISK';

    $predefined = $config->get('predefined_amounts') ?: [];
    if (!in_array($amount, array_map('intval', $predefined), TRUE)) {
      return new JsonResponse(['error' => 'Invalid gift card amount.'], 400, $this->cors());
    }

    $completeUrl = $config->get('complete_url') ?: '';
    $cancelUrl   = $config->get('cancel_url') ?: '';

    if (!$completeUrl || !$cancelUrl) {
      $this->getLogger('giftcard_core')->error('Gift card complete/cancel URLs are not configured.');
      return new JsonResponse(['error' => 'Payment gateway is not fully configured.'], 500, $this->cors());
    }

    $session = $this->paymentClient->createCheckout($amount, $currency, $completeUrl, $cancelUrl);

    if ($session === NULL) {
      $this->getLogger('giftcard_core')->error('Failed to create Rapyd checkout session for amount @amount.', ['@amount' => $amount]);
      return new JsonResponse(['error' => 'Payment service unavailable. Please try again.'], 503, $this->cors());
    }

    $checkoutData = [
      'sender_name'     => $data['name'],
      'sender_email'    => $data['email'],
      'recipient_name'  => $data['name'],
      'recipient_email' => $data['email'],
      'message'         => $data['notes'] ?? '',
      'amount'          => $amount,
      'currency'        => $currency,
      'payment_id'      => $session['payment_id'],
    ];

    $this->giftCardService->storeCheckoutData($checkoutData);
    $this->giftCardService->storeCheckoutDataByPaymentId($session['payment_id'], $checkoutData);

    $this->flood->register(self::FLOOD_EVENT, 3600, $ip);

    return new JsonResponse(['checkout_url' => $session['redirect_url']], 200, $this->cors());
  }

  /**
   * Returns CORS headers.
   *
   * @return array<string, string>
   */
  private function cors(): array {
    return [
      'Access-Control-Allow-Origin'  => '*',
      'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
      'Access-Control-Allow-Headers' => 'Content-Type',
    ];
  }

}
