<?php

namespace Drupal\rapyd_checkout;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;

/**
 * API client for the Rapyd Hosted Checkout API.
 */
class RapydClient {

  /**
   * The Rapyd API base URL (sandbox or live).
   */
  private string $baseUrl;

  public function __construct(
    private string $accessKey,
    private string $secretKey,
    bool $sandbox,
    private ClientInterface $http,
    private LoggerInterface $logger,
  ) {
    $this->baseUrl = $sandbox
      ? 'https://sandboxapi.rapyd.net'
      : 'https://api.rapyd.net';
  }

  /**
   * Creates a Rapyd Hosted Checkout page and returns the redirect URL.
   *
   * @param int $order_id
   *   The Commerce order ID used as the merchant reference.
   * @param int $amount
   *   Amount in the smallest currency unit (e.g. cents or aurar).
   * @param string $currency
   *   ISO 4217 currency code (e.g. ISK, USD, EUR).
   * @param string $country
   *   ISO 3166-1 alpha-2 country code (e.g. IS, US, DE).
   * @param string $complete_url
   *   Absolute URL to redirect to after successful payment.
   * @param string $error_url
   *   Absolute URL to redirect to on payment failure.
   *
   * @return array{redirect_url: string, checkout_id: string}
   *   Redirect URL and checkout ID from Rapyd.
   *
   * @throws \RuntimeException
   */
  public function createCheckout(
    int $order_id,
    int $amount,
    string $currency,
    string $country,
    string $complete_url,
    string $error_url,
  ): array {
    if (empty($this->accessKey) || empty($this->secretKey)) {
      throw new \RuntimeException('Rapyd API credentials are not configured.');
    }

    $path = '/v1/checkout';
    $body = [
      'amount' => $amount,
      'currency' => $currency,
      'country' => $country,
      'complete_payment_url' => $complete_url,
      'error_payment_url' => $error_url,
      'merchant_reference_id' => 'rapyd-order-' . $order_id,
      'metadata' => ['order_id' => $order_id],
    ];
    $body_str = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $headers = $this->buildHeaders('POST', $path, $body_str);

    try {
      $response = $this->http->request('POST', $this->baseUrl . $path, [
        'headers' => $headers,
        'body' => $body_str,
      ]);
      $decoded = json_decode((string) $response->getBody(), TRUE);

      if (!is_array($decoded) || empty($decoded['data']['redirect_url'])) {
        throw new \RuntimeException('Rapyd response missing redirect_url.');
      }

      return [
        'redirect_url' => $decoded['data']['redirect_url'],
        'checkout_id' => $decoded['data']['id'] ?? '',
      ];
    }
    catch (GuzzleException $e) {
      $this->logger->error('Rapyd createCheckout error: @msg', ['@msg' => $e->getMessage()]);
      throw new \RuntimeException('Rapyd API request failed: ' . $e->getMessage(), 0, $e);
    }
  }

  /**
   * Fetches the status of a checkout page by ID.
   *
   * @return array
   *   Decoded Rapyd checkout data.
   *
   * @throws \RuntimeException
   */
  public function getCheckoutStatus(string $checkout_id): array {
    $path = '/v1/checkout/' . $checkout_id;
    $headers = $this->buildHeaders('GET', $path);

    try {
      $response = $this->http->request('GET', $this->baseUrl . $path, [
        'headers' => $headers,
      ]);
      $decoded = json_decode((string) $response->getBody(), TRUE);

      if (!is_array($decoded)) {
        $this->logger->error(
          'Rapyd getCheckoutStatus: unexpected response for @id.',
          ['@id' => $checkout_id]
        );
        throw new \RuntimeException('Could not parse checkout status response.');
      }

      if (empty($decoded['data'])) {
        $this->logger->warning(
          'Rapyd getCheckoutStatus: missing data field for @id.',
          ['@id' => $checkout_id]
        );
      }

      return $decoded['data'] ?? [];
    }
    catch (GuzzleException $e) {
      $this->logger->error('Rapyd getCheckoutStatus error: @msg', ['@msg' => $e->getMessage()]);
      throw new \RuntimeException('Could not fetch checkout status: ' . $e->getMessage(), 0, $e);
    }
  }

  /**
   * Issues a refund for a Rapyd payment.
   *
   * @param string $payment_id
   *   The Rapyd payment ID to refund.
   * @param int $amount
   *   Amount to refund in the smallest currency unit.
   *
   * @throws \RuntimeException
   */
  public function refund(string $payment_id, int $amount): void {
    $path = '/v1/refunds';
    $body = json_encode([
      'payment' => $payment_id,
      'amount' => $amount,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $headers = $this->buildHeaders('POST', $path, $body);

    try {
      $response = $this->http->request('POST', $this->baseUrl . $path, [
        'headers' => $headers,
        'body' => $body,
      ]);
      $decoded = json_decode((string) $response->getBody(), TRUE);

      if (!is_array($decoded) || ($decoded['status']['status'] ?? '') !== 'SUCCESS') {
        $message = $decoded['status']['message'] ?? 'unknown error';
        throw new \RuntimeException('Rapyd refund failed: ' . $message);
      }
    }
    catch (GuzzleException $e) {
      $this->logger->error('Rapyd refund error: @msg', ['@msg' => $e->getMessage()]);
      throw new \RuntimeException('Rapyd refund API request failed: ' . $e->getMessage(), 0, $e);
    }
  }

  /**
   * Verifies an incoming Rapyd webhook signature.
   *
   * Signature is always verified regardless of sandbox/live mode.
   */
  public function verifyWebhook(
    string $raw_body,
    string $salt,
    string $timestamp,
    string $signature,
    string $path,
  ): bool {
    if (empty($this->secretKey) || empty($this->accessKey)) {
      return FALSE;
    }
    $to_sign = 'post' . $path . $salt . $timestamp
      . $this->accessKey . $this->secretKey . $raw_body;
    $expected = base64_encode(hash_hmac('sha256', $to_sign, $this->secretKey));
    return hash_equals($expected, $signature);
  }

  /**
   * Builds signed HMAC request headers for the Rapyd API.
   *
   * @param string $method
   *   HTTP method in uppercase (e.g. 'GET', 'POST').
   * @param string $path
   *   API endpoint path (e.g. '/v1/checkout').
   * @param string $body_str
   *   JSON-encoded request body, empty string for GET requests.
   *
   * @return array<string, string>
   *   Associative array of HTTP headers.
   */
  private function buildHeaders(string $method, string $path, string $body_str = ''): array {
    $salt = bin2hex(random_bytes(8));
    $timestamp = (string) time();
    $to_sign = strtolower($method) . $path . $salt . $timestamp
      . $this->accessKey . $this->secretKey . $body_str;
    $signature = base64_encode(hash_hmac('sha256', $to_sign, $this->secretKey));

    return [
      'access_key' => $this->accessKey,
      'salt' => $salt,
      'timestamp' => $timestamp,
      'signature' => $signature,
      'Content-Type' => 'application/json',
    ];
  }

}
