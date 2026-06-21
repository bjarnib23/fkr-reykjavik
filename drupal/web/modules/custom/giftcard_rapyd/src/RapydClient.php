<?php

namespace Drupal\giftcard_rapyd;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\giftcard_core\PaymentClientInterface;
use Drupal\key\KeyRepositoryInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\HttpFoundation\Request;

/**
 * Rapyd implementation of PaymentClientInterface.
 *
 * Credentials are loaded from the Key module via giftcard_rapyd.settings.
 * Every outbound request is signed with HMAC-SHA256. Inbound webhooks are
 * verified for signature, timestamp skew, and idempotency-key replay before
 * the payload is trusted.
 */
class RapydClient implements PaymentClientInterface {

  /**
   * Rapyd sandbox API base URL.
   */
  private const SANDBOX_BASE = 'https://sandboxapi.rapyd.net';

  /**
   * Rapyd production API base URL.
   */
  private const LIVE_BASE = 'https://api.rapyd.net';

  /**
   * Key-value collection used to store recently-seen webhook idempotency keys.
   */
  private const SALTS_COLLECTION = 'giftcard_rapyd.webhook_salts';

  /**
   * Constructs a new RapydClient.
   *
   * @param \GuzzleHttp\ClientInterface $httpClient
   *   The HTTP client.
   * @param \Drupal\key\KeyRepositoryInterface $keyRepository
   *   The key repository for loading API credentials.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   The logger channel factory.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface $keyValueExpirable
   *   The expirable key-value store factory (used for replay protection).
   */
  public function __construct(
    private readonly ClientInterface $httpClient,
    private readonly KeyRepositoryInterface $keyRepository,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly LoggerChannelFactoryInterface $loggerFactory,
    private readonly LanguageManagerInterface $languageManager,
    private readonly KeyValueExpirableFactoryInterface $keyValueExpirable,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function createCheckout(int $amount, string $currency, string $completeUrl, string $cancelUrl): ?array {
    $country = $this->configFactory->get('giftcard_rapyd.settings')->get('rapyd_country');

    $body = json_encode([
      'amount'                => $amount,
      'currency'              => $currency,
      'country'               => $country,
      'complete_checkout_url' => $completeUrl,
      'cancel_checkout_url'   => $cancelUrl,
      'language'              => $this->languageManager->getCurrentLanguage()->getId(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $response = $this->request('POST', '/v1/checkout', $body);

    if ($response === NULL) {
      return NULL;
    }

    $redirect = $response['data']['redirect_url'] ?? NULL;
    $id       = $response['data']['id'] ?? NULL;

    if ($redirect === NULL || $id === NULL) {
      $this->loggerFactory->get('giftcard_core')->error('Rapyd checkout response missing redirect_url or id.');
      return NULL;
    }

    return ['redirect_url' => $redirect, 'payment_id' => $id];
  }

  /**
   * {@inheritdoc}
   *
   * Verifies the Rapyd HMAC-SHA256 signature, rejects requests with a
   * timestamp more than 5 minutes from the server clock, and blocks
   * replayed idempotency keys for 10 minutes.
   */
  public function verifyWebhook(Request $request): bool {
    $body      = $request->getContent();
    $salt      = $request->headers->get('rapyd-idempotency', '');
    $timestamp = $request->headers->get('rapyd-timestamp', '');
    $signature = $request->headers->get('rapyd-signature', '');
    $path      = $request->getPathInfo();

    $access = $this->getAccess();
    $secret = $this->getSecret();
    if ($access === NULL || $secret === NULL) {
      return FALSE;
    }

    $toSign   = 'post' . $path . $salt . $timestamp . $access . $secret . $body;
    $expected = base64_encode(hash_hmac('sha256', $toSign, $secret));
    if (!hash_equals($expected, $signature)) {
      $this->loggerFactory->get('giftcard_core')->warning('Webhook rejected: invalid signature.');
      return FALSE;
    }

    if (abs(time() - (int) $timestamp) > 300) {
      $this->loggerFactory->get('giftcard_core')->warning('Webhook rejected: timestamp out of acceptable range.');
      return FALSE;
    }

    $seenSalts = $this->keyValueExpirable->get(self::SALTS_COLLECTION);
    if ($seenSalts->get($salt) !== NULL) {
      $this->loggerFactory->get('giftcard_core')->warning(
        'Webhook rejected: duplicate idempotency key @salt.',
        ['@salt' => $salt]
      );
      return FALSE;
    }
    $seenSalts->setWithExpire($salt, TRUE, 600);

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function extractCompletedPaymentId(Request $request): ?string {
    $payload = json_decode($request->getContent(), TRUE);
    if (($payload['type'] ?? '') !== 'PAYMENT_COMPLETED') {
      return NULL;
    }
    return $payload['data']['id'] ?? NULL;
  }

  /**
   * Sends a signed request to the Rapyd API.
   *
   * @param string $method
   *   The HTTP method (GET, POST, etc.).
   * @param string $path
   *   The API endpoint path.
   * @param string $body
   *   (optional) The JSON-encoded request body.
   *
   * @return array|null
   *   The decoded JSON response, or NULL on failure.
   */
  private function request(string $method, string $path, string $body = ''): ?array {
    $access = $this->getAccess();
    $secret = $this->getSecret();

    if ($access === NULL || $secret === NULL) {
      $this->loggerFactory->get('giftcard_core')->error('Rapyd API credentials are not configured.');
      return NULL;
    }

    $salt      = bin2hex(random_bytes(8));
    $timestamp = (string) time();
    $toSign    = strtolower($method) . $path . $salt . $timestamp . $access . $secret . $body;
    $signature = base64_encode(hash_hmac('sha256', $toSign, $secret));

    $headers = [
      'Content-Type' => 'application/json',
      'access_key'   => $access,
      'salt'         => $salt,
      'timestamp'    => $timestamp,
      'signature'    => $signature,
    ];

    try {
      $response = $this->httpClient->request(strtoupper($method), $this->baseUrl() . $path, [
        'headers' => $headers,
        'body'    => $body,
      ]);

      return json_decode($response->getBody()->getContents(), TRUE);
    }
    catch (RequestException $e) {
      $this->loggerFactory->get('giftcard_core')->error(
        'Rapyd API request failed: @message',
        ['@message' => $e->getMessage()]
      );
      return NULL;
    }
  }

  /**
   * Returns the Rapyd API base URL based on sandbox setting.
   *
   * @return string
   *   The base URL.
   */
  private function baseUrl(): string {
    $sandbox = (bool) $this->configFactory->get('giftcard_rapyd.settings')->get('rapyd_sandbox');
    return $sandbox ? self::SANDBOX_BASE : self::LIVE_BASE;
  }

  /**
   * Loads the Rapyd access key from the Key module.
   *
   * @return string|null
   *   The access key value, or NULL if not configured.
   */
  private function getAccess(): ?string {
    $keyId = $this->configFactory->get('giftcard_rapyd.settings')->get('rapyd_access_key_id');
    if (empty($keyId)) {
      return NULL;
    }
    $key = $this->keyRepository->getKey($keyId);
    return $key ? $key->getKeyValue() : NULL;
  }

  /**
   * Loads the Rapyd secret key from the Key module.
   *
   * @return string|null
   *   The secret key value, or NULL if not configured.
   */
  private function getSecret(): ?string {
    $keyId = $this->configFactory->get('giftcard_rapyd.settings')->get('rapyd_secret_key_id');
    if (empty($keyId)) {
      return NULL;
    }
    $key = $this->keyRepository->getKey($keyId);
    return $key ? $key->getKeyValue() : NULL;
  }

}
