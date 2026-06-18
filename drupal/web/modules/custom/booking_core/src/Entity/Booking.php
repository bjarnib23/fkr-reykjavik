<?php

namespace Drupal\booking_core\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\booking_core\BookingInterface;

/**
 * Defines the Booking entity.
 */
#[ContentEntityType(
  id: 'booking',
  label: new TranslatableMarkup('Booking'),
  label_collection: new TranslatableMarkup('Bookings'),
  handlers: [
    'storage'        => 'Drupal\Core\Entity\Sql\SqlContentEntityStorage',
    'access'         => 'Drupal\Core\Entity\EntityAccessControlHandler',
    'list_builder'   => 'Drupal\booking_core\BookingListBuilder',
    'view_builder'   => 'Drupal\booking_core\BookingViewBuilder',
    'form'           => [
      'delete' => 'Drupal\booking_core\Form\BookingDeleteForm',
    ],
    'route_provider' => [
      'html' => 'Drupal\Core\Entity\Routing\AdminHtmlRouteProvider',
    ],
  ],
  base_table: 'booking',
  list_cache_tags: ['booking_list'],
  admin_permission: 'administer booking',
  entity_keys: [
    'id'    => 'id',
    'uuid'  => 'uuid',
    'label' => 'name',
  ],
  links: [
    'canonical'   => '/admin/bookings/{booking}',
    'delete-form' => '/admin/bookings/{booking}/delete',
    'collection'  => '/admin/bookings',
  ],
)]
class Booking extends ContentEntityBase implements BookingInterface {

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  public function getName(): string {
    return $this->get('name')->value ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function setName(string $name): static {
    $this->set('name', $name);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getEmail(): string {
    return $this->get('email')->value ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function setEmail(string $email): static {
    $this->set('email', $email);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPhone(): string {
    return $this->get('phone')->value ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function setPhone(string $phone): static {
    $this->set('phone', $phone);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getService(): string {
    return $this->get('service')->value ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function setService(string $service): static {
    $this->set('service', $service);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getDate(): string {
    return $this->get('date')->value ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function setDate(string $date): static {
    $this->set('date', $date);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getNotes(): string {
    return $this->get('notes')->value ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function setNotes(string $notes): static {
    $this->set('notes', $notes);
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
  public function getChangedTime(): int {
    return (int) $this->get('changed')->value;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('ID'))
      ->setReadOnly(TRUE)
      ->setSetting('unsigned', TRUE);

    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(new TranslatableMarkup('UUID'))
      ->setReadOnly(TRUE);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Full name'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('view', ['label' => 'above', 'type' => 'string', 'weight' => 0])
      ->setDisplayOptions('form', ['type' => 'string_textfield', 'weight' => 0])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['email'] = BaseFieldDefinition::create('email')
      ->setLabel(new TranslatableMarkup('Email'))
      ->setRequired(TRUE)
      ->setDisplayOptions('view', ['label' => 'above', 'type' => 'email_mailto', 'weight' => 1])
      ->setDisplayOptions('form', ['type' => 'email_default', 'weight' => 1])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['phone'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Phone'))
      ->setSetting('max_length', 50)
      ->setDisplayOptions('view', ['label' => 'above', 'type' => 'string', 'weight' => 2])
      ->setDisplayOptions('form', ['type' => 'string_textfield', 'weight' => 2])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['date'] = BaseFieldDefinition::create('datetime')
      ->setLabel(new TranslatableMarkup('Appointment date'))
      ->setRequired(TRUE)
      ->setDisplayOptions('view', ['label' => 'above', 'type' => 'datetime_default', 'weight' => 3])
      ->setDisplayOptions('form', ['type' => 'datetime_default', 'weight' => 3])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['service'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Service'))
      ->setSetting('max_length', 255)
      ->setDisplayOptions('view', ['label' => 'above', 'type' => 'string', 'weight' => 4])
      ->setDisplayOptions('form', ['type' => 'string_textfield', 'weight' => 4])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['notes'] = BaseFieldDefinition::create('string_long')
      ->setLabel(new TranslatableMarkup('Notes'))
      ->setDisplayOptions('view', ['label' => 'above', 'type' => 'basic_string', 'weight' => 5])
      ->setDisplayOptions('form', ['type' => 'string_textarea', 'weight' => 5])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(new TranslatableMarkup('Created'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(new TranslatableMarkup('Changed'));

    return $fields;
  }

}
