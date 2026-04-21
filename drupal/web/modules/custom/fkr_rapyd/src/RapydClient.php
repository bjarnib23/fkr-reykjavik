<?php

namespace Drupal\fkr_rapyd;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

/**
 * API client for Rapyd Hosted Checkout — creates checkout sessions and verifies webhooks.
 */
class RapydClient {

  private string $accessKey;
  private string $secretKey;
  private bool $sandbox;
  private string $frontendUrl;
  private ClientInterface $http;
  private \Psr\Log\LoggerInterface $logger;

  public function __construct(
    ConfigFactoryInterface $config_factory,
    ClientInterface $http_client,
    LoggerChannelFactoryInterface $logger_factory,
  ) {
    $config = $config_factory->get('fkr_rapyd.settings');
    $this->accessKey   = $config->get('access_key') ?? '';
    $this->secretKey   = $config->get('secret_key') ?? '';
    $this->sandbox     = (bool) ($config->get('sandbox') ?? TRUE);
    $this->frontendUrl = rtrim($config->get('frontend_url') ?? 'http://localhost:5173', '/');
    $this->http        = $http_client;
    $this->logger      = $logger_factory->get('fkr_rapyd');
  }

  /**
   * Creates a Rapyd Hosted Checkout session.
   *
   * @param int    $order_id  Commerce order ID
   * @param int    $amount    Amount in whole ISK (e.g. 10000)
   * @param string $email     Buyer email
   *
   * @return array{redirect_url: string}
   *
   * @throws \RuntimeException on API error or missing credentials
   */
  public function createCheckout(int $order_id, int $amount, string $email): array {
    if (empty($this->accessKey) || empty($this->secretKey)) {
      throw new \RuntimeException('Rapyd API credentials are not configured.');
    }

    $base    = $this->sandbox ? 'https://sandboxapi.rapyd.net' : 'https://api.rapyd.net';
    $path    = '/v1/checkout';
    $body    = [
      'amount'                => $amount,
      'currency'              => 'ISK',
      'country'               => 'IS',
      'complete_payment_url'  => $this->frontendUrl . '/giftcard/thank-you?order_id=' . $order_id,
      'error_payment_url'     => $this->frontendUrl . '/giftcard/cancel',
      'merchant_reference_id' => 'fkr-order-' . $order_id,
      'metadata'              => ['order_id' => $order_id],
      'requested_by'          => $email,
    ];

    $body_str = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $headers  = $this->buildHeaders('post', $path, $body_str);

    try {
      $response = $this->http->request('POST', $base . $path, [
        'headers' => $headers,
        'body'    => $body_str,
      ]);

      $decoded = json_decode((string) $response->getBody(), TRUE);

      if (!is_array($decoded) || empty($decoded['data']['redirect_url'])) {
        throw new \RuntimeException('Rapyd response missing or malformed.');
      }

      return ['redirect_url' => $decoded['data']['redirect_url']];
    }
    catch (GuzzleException $e) {
      $this->logger->error('Rapyd API error: @msg', ['@msg' => $e->getMessage()]);
      throw new \RuntimeException('Rapyd API request failed: ' . $e->getMessage(), 0, $e);
    }
  }

  /**
   * Verifies a Rapyd webhook signature.
   *
   * @param string $raw_body   Raw POST body string
   * @param string $salt       From rapyd-idempotency header
   * @param string $timestamp  From rapyd-timestamp header
   * @param string $signature  From rapyd-signature header
   * @param string $path       Request path used in signature computation
   *
   * @return bool
   */
  public function verifyWebhook(string $raw_body, string $salt, string $timestamp, string $signature, string $path = '/api/fkr/rapyd/webhook'): bool {
    if (empty($this->secretKey)) {
      return FALSE;
    }
    $to_sign  = 'post' . $path . $salt . $timestamp . $this->accessKey . $this->secretKey . $raw_body;
    $expected = base64_encode(hash_hmac('sha256', $to_sign, $this->secretKey, TRUE));
    return hash_equals($expected, $signature);
  }

  private function buildHeaders(string $method, string $path, string $body_str = ''): array {
    $salt      = bin2hex(random_bytes(8));
    $timestamp = (string) time();
    $to_sign   = strtolower($method) . $path . $salt . $timestamp . $this->accessKey . $this->secretKey . $body_str;
    $signature = base64_encode(hash_hmac('sha256', $to_sign, $this->secretKey, TRUE));

    return [
      'access_key'   => $this->accessKey,
      'salt'         => $salt,
      'timestamp'    => $timestamp,
      'signature'    => $signature,
      'Content-Type' => 'application/json',
    ];
  }

}
