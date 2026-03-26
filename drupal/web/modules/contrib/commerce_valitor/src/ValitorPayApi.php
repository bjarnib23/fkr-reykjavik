<?php

namespace Drupal\commerce_valitor;

use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\Component\Serialization\Json;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;

/**
 * The Valitor Pay API.
 *
 * @package Drupal\commerce_valitor
 */
class ValitorPayApi implements ValitorPayApiInterface {

  /**
   * API Version.
   */
  const API_VERSION = '2.0';

  /**
   * Endpoints urls.
   */
  const TEST_ENDPOINT = 'https://uat.valitorpay.com/';
  const LIVE_ENDPOINT = 'https://valitorpay.com/';

  /**
   * API key.
   *
   * @var string
   */
  private $apiKey;

  /**
   * Environment: live or test.
   *
   * @var string
   */
  private $environment;

  /**
   * Represents merchant agreement number.
   *
   * Must be a numeric value with no leading zeros.
   *
   * @var string
   */
  private $agreementNumber;

  /**
   * Represents merchant terminal identifier.
   *
   * Must be a numeric value with no leading zeros.
   *
   * @var string
   */
  private $terminalId;

  /**
   * Http client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $client;

  /**
   * Logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * ValitorPayApi constructor.
   *
   * @param \GuzzleHttp\ClientInterface $client
   *   HTTP client.
   * @param string $api_key
   *   Merchant API key.
   * @param int $agreement_number
   *   Merchant agreement number.
   * @param int $terminal_id
   *   Merchant terminal identifier.
   * @param string $environment
   *   Enum with 2 values 'live' or 'test'.
   * @param \Psr\Log\LoggerInterface|null $logger
   *   A logger instance.
   */
  public function __construct(ClientInterface $client, $api_key, $agreement_number, $terminal_id, $environment, ?LoggerInterface $logger = NULL) {
    $this->setClient($client)
      ->setApiKey($api_key)
      ->setAgreementNumber($agreement_number)
      ->setTerminalId($terminal_id)
      ->setEnvironment($environment);
    if ($logger instanceof LoggerInterface) {
      $this->setLogger($logger);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setClient(ClientInterface $client) {
    $this->client = $client;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setLogger(LoggerInterface $logger) {
    $this->logger = $logger;
    return $this;
  }

  /**
   * Global setter.
   *
   * @param string $key
   *   Name of the property.
   * @param mixed $value
   *   Value to set.
   *
   * @return ValitorPayApiInterface
   *   The Valitor Pay API interface.
   *
   * @throws \Drupal\commerce_payment\Exception\PaymentGatewayException
   */
  protected function set($key, $value) {
    if (!property_exists($this, $key)) {
      throw new PaymentGatewayException("The property $key is undefined.");
    }
    $this->{$key} = $value;

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getApiKey() {
    return $this->apiKey;
  }

  /**
   * {@inheritdoc}
   */
  public function setApiKey($api_key) {
    if (empty($api_key)) {
      throw new PaymentGatewayException('No API key provided.');
    }
    $this->set('apiKey', $api_key);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getEnvironment() {
    return $this->environment;
  }

  /**
   * {@inheritdoc}
   */
  public function setEnvironment($environment) {
    if (!in_array($environment, ['live', 'test'])) {
      throw new PaymentGatewayException("The specified environment $environment is not valid.");
    }

    return $this->set('environment', $environment);
  }

  /**
   * {@inheritdoc}
   */
  public function getEnvironmentURL() {
    $environment_url = self::TEST_ENDPOINT;
    if ($this->getEnvironment() == 'live') {
      $environment_url = self::LIVE_ENDPOINT;
    }
    return $environment_url;
  }

  /**
   * {@inheritdoc}
   */
  public function getTerminalId() {
    return $this->terminalId;
  }

  /**
   * {@inheritdoc}
   */
  public function setTerminalId($terminal_id) {
    if (!empty($terminal_id) && !is_numeric($terminal_id)) {
      throw new PaymentGatewayException('The POS terminal ID must be numeric.');
    }
    return $this->set('terminalId', $terminal_id);
  }

  /**
   * {@inheritdoc}
   */
  public function getAgreementNumber() {
    return $this->agreementNumber;
  }

  /**
   * {@inheritdoc}
   */
  public function setAgreementNumber($agreement_number) {
    if (!empty($agreement_number) && !is_numeric($agreement_number)) {
      throw new PaymentGatewayException('The agreement number must be numeric.');
    }
    return $this->set('agreementNumber', $agreement_number);
  }

  /**
   * General send request method.
   *
   * @param string $method_uri
   *   The uri part of the request uri.
   * @param array $payload
   *   The payload array.
   *
   * @return array
   *   The response array.
   *   If consists or 'error' element, then request failed.
   */
  protected function sendRequest($method_uri, array $payload) {
    try {
      $response = $this->client->post(
        $this->getEnvironmentURL() . $method_uri,
        [
          'json' => $payload,
          'headers' => [
            'valitorpay-api-version' => self::API_VERSION,
            'Authorization' => 'APIKey ' . $this->getApiKey(),
          ],
        ]
      );
      return Json::decode($response->getBody());
    }
    catch (RequestException $exception) {
      $this->logger->error($exception->getMessage());
      $content = $exception->getResponse()->getBody()->getContents();
      return Json::decode($content);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function createVirtualCard($payload) {
    return $this->sendRequest('VirtualCard/CreateVirtualCard', $payload);
  }

  /**
   * {@inheritdoc}
   */
  public function verifyCard($payload) {
    return $this->sendRequest('CardVerification', $payload);
  }

  /**
   * {@inheritdoc}
   */
  public function createVirtualCardPayment($payload) {
    return $this->sendRequest('Payment/VirtualCardPayment', $payload);
  }

  /**
   * {@inheritdoc}
   */
  public function updateExpirationDate($payload) {
    return $this->sendRequest('VirtualCard/UpdateExpirationDate', $payload);
  }

}
