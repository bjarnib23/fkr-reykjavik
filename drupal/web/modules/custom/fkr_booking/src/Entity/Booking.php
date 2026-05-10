<?php

namespace Drupal\fkr_booking\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * @ContentEntityType(
 *   id = "fkr_booking",
 *   label = @Translation("Booking"),
 *   base_table = "fkr_booking",
 *   admin_permission = "access administration pages",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "label" = "name",
 *   },
 * )
 */
class Booking extends ContentEntityBase {

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Name'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255);

    $fields['email'] = BaseFieldDefinition::create('email')
      ->setLabel(t('Email'))
      ->setRequired(TRUE);

    $fields['phone'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Phone'))
      ->setSetting('max_length', 50);

    $fields['date'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Date'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 30);

    $fields['service'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Service'))
      ->setSetting('max_length', 255);

    $fields['notes'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Notes'));

    $fields['wishes'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Wishes'));

    $fields['booking_status'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Booking Status'))
      ->setDefaultValue('pending')
      ->setSetting('max_length', 50);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'));

    return $fields;
  }

}
