<?php

namespace Drupal\commerce_valitor;

use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;

/**
 * The Valitor pay API interface.
 *
 * @package Drupal\commerce_valitor
 */
interface ValitorPayApiInterface {

  /**
   * Set HTTP client.
   *
   * @param \GuzzleHttp\ClientInterface $client
   *   The HTTP client.
   *
   * @return $this
   */
  public function setClient(ClientInterface $client);

  /**
   * Sets logger.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger interface.
   *
   * @return $this
   */
  public function setLogger(LoggerInterface $logger);

  /**
   * Gets API key.
   *
   * @return string
   *   The API key.
   */
  public function getApiKey();

  /**
   * Sets logger.
   *
   * @param string $api_key
   *   The API key.
   *
   * @return $this
   */
  public function setApiKey($api_key);

  /**
   * Gets current environment.
   *
   * @return string
   *   The environment name.
   */
  public function getEnvironment();

  /**
   * Sets current environment.
   *
   * @param string $environment
   *   The environment name.
   *
   * @return $this
   */
  public function setEnvironment($environment);

  /**
   * Gets environment url.
   *
   * @return string
   *   The environment URL.
   */
  public function getEnvironmentURL();

  /**
   * Gets terminal id.
   *
   * @return string
   *   The terminal ID.
   */
  public function getTerminalId();

  /**
   * Sets terminal id.
   *
   * @param string $terminal_id
   *   The terminal ID.
   *
   * @return $this
   */
  public function setTerminalId($terminal_id);

  /**
   * Gets agreement number.
   *
   * @return string
   *   The agreement number.
   */
  public function getAgreementNumber();

  /**
   * Sets agreement number.
   *
   * @param string $agreement_number
   *   The agreement number.
   *
   * @return $this
   */
  public function setAgreementNumber($agreement_number);

  /**
   * Create Virtual Card.
   *
   * @param array $payload
   *   The payload.
   *
   * @return false|mixed
   *   The response.
   */
  public function createVirtualCard(array $payload);

  /**
   * Get card verification.
   *
   * @param array $payload
   *   The payload.
   *
   * @return false|mixed
   *   The response.
   */
  public function verifyCard(array $payload);

  /**
   * Create virtual card payment.
   *
   * @param array $payload
   *   The payload.
   *
   * @return false|mixed
   *   The response.
   */
  public function createVirtualCardPayment(array $payload);

  /**
   * Updates virtual card expiration date.
   *
   * @param array $payload
   *   The payload.
   *
   * @return false|mixed
   *   The response.
   */
  public function updateExpirationDate(array $payload);

}
