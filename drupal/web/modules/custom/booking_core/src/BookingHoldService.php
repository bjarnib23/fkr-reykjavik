<?php

namespace Drupal\booking_core;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;

/**
 * Manages temporary slot holds for the React booking frontend.
 */
class BookingHoldService {

  private const TABLE = 'booking_core_slot_holds';
  private const DEFAULT_TTL = 360;

  /**
   * Constructs a BookingHoldService object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   */
  public function __construct(
    private Connection $database,
    private ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Holds a slot by datetime, returning token and expiry.
   *
   * @param string $datetime
   *   Slot datetime in site timezone (YYYY-MM-DDTHH:MM:SS).
   *
   * @return array{token: string, expires: int}|false
   *   Token/expiry array, or FALSE if slot is already held.
   */
  public function holdSlot(string $datetime): array|false {
    $this->cleanExpired();

    $active = (int) $this->database->select(self::TABLE, 'h')
      ->condition('datetime', $datetime)
      ->condition('expires', time(), '>=')
      ->countQuery()
      ->execute()
      ->fetchField();

    if ($active > 0) {
      return FALSE;
    }

    $ttl     = (int) ($this->configFactory->get('booking_core.settings')->get('hold_ttl') ?: self::DEFAULT_TTL);
    $expires = time() + $ttl;
    $token   = bin2hex(random_bytes(16));

    $this->database->insert(self::TABLE)->fields([
      'datetime' => $datetime,
      'token'    => $token,
      'expires'  => $expires,
    ])->execute();

    return ['token' => $token, 'expires' => $expires];
  }

  /**
   * Releases a hold by token.
   *
   * @param string $token
   *   The hold token.
   */
  public function releaseHold(string $token): void {
    $this->database->delete(self::TABLE)->condition('token', $token)->execute();
  }

  /**
   * Returns held H:i time strings for a given date.
   *
   * @param string $date
   *   Date in Y-m-d format.
   *
   * @return string[]
   *   Array of H:i time strings currently held for the date.
   */
  public function getHeldTimesForDate(string $date): array {
    $rows = $this->database->select(self::TABLE, 'h')
      ->fields('h', ['datetime'])
      ->condition('datetime', $date . 'T%', 'LIKE')
      ->condition('expires', time(), '>=')
      ->execute()
      ->fetchCol();

    return array_map(
      static fn(string $dt): string => substr($dt, 11, 5),
      $rows,
    );
  }

  /**
   * Removes all expired holds from the table.
   */
  public function cleanExpired(): void {
    $this->database->delete(self::TABLE)->condition('expires', time(), '<')->execute();
  }

}
