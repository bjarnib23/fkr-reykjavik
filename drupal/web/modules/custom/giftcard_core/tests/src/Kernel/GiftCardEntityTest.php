<?php

namespace Drupal\Tests\giftcard_core\Kernel;

use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\giftcard_core\Entity\GiftCard;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the GiftCard entity can be created, saved, and loaded.
 *
 * @group giftcard_core
 */
#[RunTestsInSeparateProcesses]
class GiftCardEntityTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['giftcard_core', 'key', 'options'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('gift_card');
  }

  /**
   * Tests that a GiftCard can be created, saved, and loaded.
   */
  public function testGiftCardCanBeCreatedAndLoaded(): void {
    $giftCard = GiftCard::create([
      'code'            => 'TESTCODE12345678',
      'recipient_name'  => 'Anna Sigurðardóttir',
      'recipient_email' => 'anna@example.com',
      'sender_name'     => 'Jón Jónsson',
      'sender_email'    => 'jon@example.com',
      'amount'          => 5000,
      'currency'        => 'ISK',
      'message'         => 'Til hamingju með afmælið!',
      'status'          => 'active',
    ]);

    $this->assertSame(SAVED_NEW, $giftCard->save());
    $this->assertNotEmpty($giftCard->id());

    $loaded = GiftCard::load($giftCard->id());
    $this->assertSame('TESTCODE12345678', $loaded->getCode());
    $this->assertSame('Anna Sigurðardóttir', $loaded->getRecipientName());
    $this->assertSame('anna@example.com', $loaded->getRecipientEmail());
    $this->assertSame(5000, $loaded->getAmount());
    $this->assertSame('ISK', $loaded->getCurrency());
    $this->assertSame('active', $loaded->getStatus());
  }

  /**
   * Tests that a GiftCard without a code fails validation.
   */
  public function testGiftCardRequiresCode(): void {
    $giftCard = GiftCard::create([
      'recipient_name'  => 'Anna Sigurðardóttir',
      'recipient_email' => 'anna@example.com',
      'sender_name'     => 'Jón Jónsson',
      'sender_email'    => 'jon@example.com',
      'amount'          => 5000,
      'currency'        => 'ISK',
      'status'          => 'active',
    ]);

    $violations = $giftCard->validate();
    $this->assertGreaterThan(0, $violations->count());
  }

  /**
   * Tests that sender fields and message are persisted correctly.
   */
  public function testGiftCardSenderAndMessageArePersisted(): void {
    $giftCard = GiftCard::create([
      'code'            => 'SENDERTEST123456',
      'recipient_name'  => 'Anna Sigurðardóttir',
      'recipient_email' => 'anna@example.com',
      'sender_name'     => 'Jón Jónsson',
      'sender_email'    => 'jon@example.com',
      'amount'          => 3000,
      'currency'        => 'ISK',
      'message'         => 'Til hamingju með afmælið!',
      'status'          => 'active',
    ]);
    $giftCard->save();

    $loaded = GiftCard::load($giftCard->id());
    $this->assertSame('Jón Jónsson', $loaded->getSenderName());
    $this->assertSame('jon@example.com', $loaded->getSenderEmail());
    $this->assertSame('Til hamingju með afmælið!', $loaded->getMessage());
  }

  /**
   * Tests that the gift card status can be updated.
   */
  public function testGiftCardStatusCanBeUpdated(): void {
    $giftCard = GiftCard::create([
      'code'            => 'STATUSTEST123456',
      'recipient_name'  => 'Anna Sigurðardóttir',
      'recipient_email' => 'anna@example.com',
      'sender_name'     => 'Jón Jónsson',
      'sender_email'    => 'jon@example.com',
      'amount'          => 2000,
      'currency'        => 'ISK',
      'status'          => 'active',
    ]);
    $giftCard->save();

    $giftCard->setStatus('redeemed');
    $this->assertSame(SAVED_UPDATED, $giftCard->save());

    $loaded = GiftCard::load($giftCard->id());
    $this->assertSame('redeemed', $loaded->getStatus());
  }

}
