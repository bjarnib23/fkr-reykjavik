<?php

namespace Drupal\giftcard_core\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\giftcard_core\GiftCardInterface;

/**
 * Defines the Gift Card entity.
 */
#[ContentEntityType(
  id: 'gift_card',
  label: new TranslatableMarkup('Gift Card'),
  label_collection: new TranslatableMarkup('Gift Cards'),
  handlers: [
    'storage'        => 'Drupal\Core\Entity\Sql\SqlContentEntityStorage',
    'access'         => 'Drupal\Core\Entity\EntityAccessControlHandler',
    'list_builder'   => 'Drupal\giftcard_core\GiftCardListBuilder',
    'view_builder'   => 'Drupal\Core\Entity\EntityViewBuilder',
    'form'           => [
      'default' => 'Drupal\giftcard_core\Form\GiftCardForm',
      'add'     => 'Drupal\giftcard_core\Form\GiftCardForm',
      'edit'    => 'Drupal\giftcard_core\Form\GiftCardForm',
      'delete'  => 'Drupal\giftcard_core\Form\GiftCardDeleteForm',
    ],
    'route_provider' => [
      'html' => 'Drupal\Core\Entity\Routing\AdminHtmlRouteProvider',
    ],
  ],
  base_table: 'gift_card',
  admin_permission: 'administer gift cards',
  entity_keys: [
    'id'    => 'id',
    'uuid'  => 'uuid',
    'label' => 'code',
  ],
  links: [
    'canonical'   => '/admin/gift-cards/{gift_card}',
    'add-form'    => '/admin/gift-cards/add',
    'edit-form'   => '/admin/gift-cards/{gift_card}/edit',
    'delete-form' => '/admin/gift-cards/{gift_card}/delete',
    'collection'  => '/admin/gift-cards',
  ],
)]
class GiftCard extends ContentEntityBase implements GiftCardInterface {

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  public function getCode(): string {
    return $this->get('code')->value ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function setCode(string $code): static {
    $this->set('code', $code);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getRecipientName(): string {
    return $this->get('recipient_name')->value ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function setRecipientName(string $name): static {
    $this->set('recipient_name', $name);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getRecipientEmail(): string {
    return $this->get('recipient_email')->value ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function setRecipientEmail(string $email): static {
    $this->set('recipient_email', $email);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getSenderName(): string {
    return $this->get('sender_name')->value ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function setSenderName(string $name): static {
    $this->set('sender_name', $name);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getSenderEmail(): string {
    return $this->get('sender_email')->value ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function setSenderEmail(string $email): static {
    $this->set('sender_email', $email);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getAmount(): int {
    return (int) ($this->get('amount')->value ?? 0);
  }

  /**
   * {@inheritdoc}
   */
  public function setAmount(int $amount): static {
    $this->set('amount', $amount);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrency(): string {
    return $this->get('currency')->value ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function setCurrency(string $currency): static {
    $this->set('currency', $currency);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getMessage(): string {
    return $this->get('message')->value ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function setMessage(string $message): static {
    $this->set('message', $message);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getStatus(): string {
    return $this->get('status')->value ?? 'pending';
  }

  /**
   * {@inheritdoc}
   */
  public function setStatus(string $status): static {
    $this->set('status', $status);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPaymentId(): string {
    return $this->get('payment_id')->value ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function setPaymentId(string $paymentId): static {
    $this->set('payment_id', $paymentId);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime(): int {
    return (int) $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['code'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Gift card code'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 64)
      ->setDisplayOptions('view', ['label' => 'above', 'type' => 'string', 'weight' => 0])
      ->setDisplayOptions('form', ['type' => 'string_textfield', 'weight' => 0])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['recipient_name'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Recipient name'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('view', ['label' => 'above', 'type' => 'string', 'weight' => 1])
      ->setDisplayOptions('form', ['type' => 'string_textfield', 'weight' => 1])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['recipient_email'] = BaseFieldDefinition::create('email')
      ->setLabel(new TranslatableMarkup('Recipient email'))
      ->setRequired(TRUE)
      ->setDisplayOptions('view', ['label' => 'above', 'type' => 'email_mailto', 'weight' => 2])
      ->setDisplayOptions('form', ['type' => 'email_default', 'weight' => 2])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['sender_name'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Sender name'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('view', ['label' => 'above', 'type' => 'string', 'weight' => 3])
      ->setDisplayOptions('form', ['type' => 'string_textfield', 'weight' => 3])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['sender_email'] = BaseFieldDefinition::create('email')
      ->setLabel(new TranslatableMarkup('Sender email'))
      ->setRequired(TRUE)
      ->setDisplayOptions('view', ['label' => 'above', 'type' => 'email_mailto', 'weight' => 4])
      ->setDisplayOptions('form', ['type' => 'email_default', 'weight' => 4])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['amount'] = BaseFieldDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Amount'))
      ->setRequired(TRUE)
      ->setSetting('unsigned', TRUE)
      ->setDisplayOptions('view', ['label' => 'above', 'type' => 'number_integer', 'weight' => 5])
      ->setDisplayOptions('form', ['type' => 'number', 'weight' => 5])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['currency'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Currency'))
      ->setRequired(TRUE)
      ->setDefaultValue('')
      ->setSetting('max_length', 3)
      ->setDisplayOptions('view', ['label' => 'above', 'type' => 'string', 'weight' => 6])
      ->setDisplayOptions('form', ['type' => 'string_textfield', 'weight' => 6])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['message'] = BaseFieldDefinition::create('string_long')
      ->setLabel(new TranslatableMarkup('Personal message'))
      ->setDisplayOptions('view', ['label' => 'above', 'type' => 'basic_string', 'weight' => 7])
      ->setDisplayOptions('form', ['type' => 'string_textarea', 'weight' => 7])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['status'] = BaseFieldDefinition::create('list_string')
      ->setLabel(new TranslatableMarkup('Status'))
      ->setRequired(TRUE)
      ->setDefaultValue('pending')
      ->setSetting('allowed_values', [
        'pending'   => 'Pending payment',
        'active'    => 'Active',
        'redeemed'  => 'Redeemed',
        'cancelled' => 'Cancelled',
      ])
      ->setDisplayOptions('view', ['label' => 'above', 'type' => 'list_default', 'weight' => 8])
      ->setDisplayOptions('form', ['type' => 'options_select', 'weight' => 8])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['payment_id'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Payment ID'))
      ->setSetting('max_length', 255)
      ->setDisplayOptions('view', ['label' => 'above', 'type' => 'string', 'weight' => 9])
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(new TranslatableMarkup('Created'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(new TranslatableMarkup('Changed'));

    return $fields;
  }

}
