<?php

namespace Drupal\giftcard_core;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;

/**
 * Business logic for gift card creation and state management.
 */
class GiftCardService {

  private const TEMPSTORE_COLLECTION = 'giftcard_core.checkout';
  private const KEYVALUE_COLLECTION  = 'giftcard_core.pending';

  /**
   * Constructs a new GiftCardService.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   The logger channel factory.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $tempStoreFactory
   *   The private temp store factory.
   * @param \Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface $keyValueExpirable
   *   The expirable key-value store factory.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly LoggerChannelFactoryInterface $loggerFactory,
    private readonly PrivateTempStoreFactory $tempStoreFactory,
    private readonly KeyValueExpirableFactoryInterface $keyValueExpirable,
  ) {}

  /**
   * Saves checkout data to the user's session store (for thank-you page).
   *
   * @param array $data
   *   The checkout data to store.
   */
  public function storeCheckoutData(array $data): void {
    $store = $this->tempStoreFactory->get(self::TEMPSTORE_COLLECTION);
    $store->set('checkout_data', $data);
  }

  /**
   * Retrieves checkout data from the user's session store.
   *
   * @return array|null
   *   The checkout data, or NULL if not set.
   */
  public function getCheckoutData(): ?array {
    $store = $this->tempStoreFactory->get(self::TEMPSTORE_COLLECTION);
    return $store->get('checkout_data');
  }

  /**
   * Removes checkout data from the user's session store.
   */
  public function clearCheckoutData(): void {
    $store = $this->tempStoreFactory->get(self::TEMPSTORE_COLLECTION);
    $store->delete('checkout_data');
  }

  /**
   * Saves checkout data keyed by payment ID for webhook lookup.
   *
   * Unlike PrivateTempStore, this is not tied to a user session and can
   * be read by the webhook controller running in a separate request.
   * Data expires after one hour.
   *
   * @param string $paymentId
   *   The payment provider's payment ID.
   * @param array $data
   *   The checkout data to store.
   */
  public function storeCheckoutDataByPaymentId(string $paymentId, array $data): void {
    $store = $this->keyValueExpirable->get(self::KEYVALUE_COLLECTION);
    $store->setWithExpire($paymentId, $data, 3600);
  }

  /**
   * Retrieves checkout data by payment ID for webhook processing.
   *
   * @param string $paymentId
   *   The payment provider's payment ID.
   *
   * @return array|null
   *   The checkout data, or NULL if not found or expired.
   */
  public function getCheckoutDataByPaymentId(string $paymentId): ?array {
    $store = $this->keyValueExpirable->get(self::KEYVALUE_COLLECTION);
    return $store->get($paymentId);
  }

  /**
   * Removes checkout data for a given payment ID after processing.
   *
   * @param string $paymentId
   *   The payment provider's payment ID.
   */
  public function deleteCheckoutDataByPaymentId(string $paymentId): void {
    $store = $this->keyValueExpirable->get(self::KEYVALUE_COLLECTION);
    $store->delete($paymentId);
  }

  /**
   * Creates and saves a GiftCard entity after successful payment.
   *
   * Prevents duplicate creation by checking whether a gift card with the
   * same payment ID already exists.
   *
   * @param array $data
   *   Associative array of gift card field values.
   *
   * @return \Drupal\giftcard_core\GiftCardInterface|null
   *   The saved entity, or NULL on failure.
   */
  public function createGiftCard(array $data): ?GiftCardInterface {
    if (!empty($data['payment_id'])) {
      $existing = $this->entityTypeManager
        ->getStorage('gift_card')
        ->loadByProperties(['payment_id' => $data['payment_id']]);

      if (!empty($existing)) {
        $this->loggerFactory->get('giftcard_core')->warning(
          'Duplicate gift card creation prevented for payment @id.',
          ['@id' => $data['payment_id']]
        );
        return reset($existing);
      }
    }

    try {
      /** @var \Drupal\giftcard_core\GiftCardInterface $giftCard */
      $giftCard = $this->entityTypeManager->getStorage('gift_card')->create([
        'code'             => $data['code'] ?? $this->generateCode(),
        'recipient_name'   => $data['recipient_name'] ?? '',
        'recipient_email'  => $data['recipient_email'] ?? '',
        'sender_name'      => $data['sender_name'] ?? '',
        'sender_email'     => $data['sender_email'] ?? '',
        'amount'           => (int) ($data['amount'] ?? 0),
        'currency'         => $data['currency'] ?? '',
        'message'          => $data['message'] ?? '',
        'status'           => 'active',
        'payment_id' => $data['payment_id'] ?? '',
      ]);

      $giftCard->save();
      return $giftCard;
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('giftcard_core')->error(
        'Failed to create gift card: @message',
        ['@message' => $e->getMessage()]
      );
      return NULL;
    }
  }

  /**
   * Loads a gift card entity by its unique code.
   *
   * @param string $code
   *   The gift card code.
   *
   * @return \Drupal\giftcard_core\GiftCardInterface|null
   *   The gift card entity, or NULL if not found.
   */
  public function loadByCode(string $code): ?GiftCardInterface {
    $results = $this->entityTypeManager
      ->getStorage('gift_card')
      ->loadByProperties(['code' => $code]);

    return !empty($results) ? reset($results) : NULL;
  }

  /**
   * Generates a unique uppercase alphanumeric gift card code.
   *
   * @return string
   *   A unique 16-character gift card code.
   */
  public function generateCode(): string {
    do {
      $raw    = strtoupper(str_replace(['+', '/', '='], '', base64_encode(random_bytes(16))));
      $code   = substr($raw, 0, 16);
      $exists = $this->entityTypeManager
        ->getStorage('gift_card')
        ->loadByProperties(['code' => $code]);
    } while (strlen($code) < 16 || !empty($exists));

    return $code;
  }

}
