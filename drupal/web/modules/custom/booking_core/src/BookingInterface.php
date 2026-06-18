<?php

namespace Drupal\booking_core;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Provides an interface for Booking entities.
 */
interface BookingInterface extends ContentEntityInterface {

  /**
   * Gets the full name of the person who made the booking.
   */
  public function getName(): string;

  /**
   * Sets the full name.
   */
  public function setName(string $name): static;

  /**
   * Gets the email address.
   */
  public function getEmail(): string;

  /**
   * Sets the email address.
   */
  public function setEmail(string $email): static;

  /**
   * Gets the phone number.
   */
  public function getPhone(): string;

  /**
   * Sets the phone number.
   */
  public function setPhone(string $phone): static;

  /**
   * Gets the booked service.
   */
  public function getService(): string;

  /**
   * Sets the booked service.
   */
  public function setService(string $service): static;

  /**
   * Gets the appointment date/time as an ISO 8601 string.
   */
  public function getDate(): string;

  /**
   * Sets the appointment date/time from an ISO 8601 string.
   */
  public function setDate(string $date): static;

  /**
   * Gets the notes.
   */
  public function getNotes(): string;

  /**
   * Sets the notes.
   */
  public function setNotes(string $notes): static;

  /**
   * Gets the creation timestamp.
   */
  public function getCreatedTime(): int;

  /**
   * Gets the last-modified timestamp.
   */
  public function getChangedTime(): int;

}
