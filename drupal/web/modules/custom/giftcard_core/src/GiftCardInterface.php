<?php

namespace Drupal\giftcard_core;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;

/**
 * Provides an interface for GiftCard entities.
 */
interface GiftCardInterface extends ContentEntityInterface, EntityChangedInterface {

  /**
   * Gets the unique gift card code.
   *
   * @return string
   *   The gift card code.
   */
  public function getCode(): string;

  /**
   * Sets the gift card code.
   *
   * @param string $code
   *   The gift card code.
   *
   * @return static
   */
  public function setCode(string $code): static;

  /**
   * Gets the recipient's full name.
   *
   * @return string
   *   The recipient name.
   */
  public function getRecipientName(): string;

  /**
   * Sets the recipient's full name.
   *
   * @param string $name
   *   The recipient name.
   *
   * @return static
   */
  public function setRecipientName(string $name): static;

  /**
   * Gets the recipient's email address.
   *
   * @return string
   *   The recipient email.
   */
  public function getRecipientEmail(): string;

  /**
   * Sets the recipient's email address.
   *
   * @param string $email
   *   The recipient email.
   *
   * @return static
   */
  public function setRecipientEmail(string $email): static;

  /**
   * Gets the sender's full name.
   *
   * @return string
   *   The sender name.
   */
  public function getSenderName(): string;

  /**
   * Sets the sender's full name.
   *
   * @param string $name
   *   The sender name.
   *
   * @return static
   */
  public function setSenderName(string $name): static;

  /**
   * Gets the sender's email address.
   *
   * @return string
   *   The sender email.
   */
  public function getSenderEmail(): string;

  /**
   * Sets the sender's email address.
   *
   * @param string $email
   *   The sender email.
   *
   * @return static
   */
  public function setSenderEmail(string $email): static;

  /**
   * Gets the gift card amount in the major currency unit (e.g. 5000 ISK).
   *
   * Amounts are stored and sent to the payment provider as whole units,
   * not in the smallest currency unit (not cents).
   *
   * @return int
   *   The amount in the major currency unit.
   */
  public function getAmount(): int;

  /**
   * Sets the gift card amount.
   *
   * @param int $amount
   *   The amount in the major currency unit.
   *
   * @return static
   */
  public function setAmount(int $amount): static;

  /**
   * Gets the ISO 4217 currency code.
   *
   * @return string
   *   The currency code.
   */
  public function getCurrency(): string;

  /**
   * Sets the ISO 4217 currency code.
   *
   * @param string $currency
   *   The currency code.
   *
   * @return static
   */
  public function setCurrency(string $currency): static;

  /**
   * Gets the personal message from the sender.
   *
   * @return string
   *   The message.
   */
  public function getMessage(): string;

  /**
   * Sets the personal message from the sender.
   *
   * @param string $message
   *   The message.
   *
   * @return static
   */
  public function setMessage(string $message): static;

  /**
   * Gets the gift card status.
   *
   * @return string
   *   One of: pending, active, redeemed, cancelled.
   */
  public function getStatus(): string;

  /**
   * Sets the gift card status.
   *
   * @param string $status
   *   One of: pending, active, redeemed, cancelled.
   *
   * @return static
   */
  public function setStatus(string $status): static;

  /**
   * Gets the payment provider's payment ID.
   *
   * @return string
   *   The payment ID.
   */
  public function getPaymentId(): string;

  /**
   * Sets the payment provider's payment ID.
   *
   * @param string $paymentId
   *   The payment ID.
   *
   * @return static
   */
  public function setPaymentId(string $paymentId): static;

  /**
   * Gets the Unix timestamp when the gift card was created.
   *
   * @return int
   *   The creation timestamp.
   */
  public function getCreatedTime(): int;

}
