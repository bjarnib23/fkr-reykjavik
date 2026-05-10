<?php
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;

$em = \Drupal::service('entity_field.manager');

if (!FieldStorageConfig::loadByName('node', 'field_success_image')) {
  FieldStorageConfig::create([
    'field_name'  => 'field_success_image',
    'entity_type' => 'node',
    'type'        => 'image',
    'cardinality' => 1,
  ])->save();
  $em->clearCachedFieldDefinitions();
}

if (!FieldConfig::loadByName('node', 'booking_page', 'field_success_image')) {
  FieldConfig::create([
    'field_name'  => 'field_success_image',
    'entity_type' => 'node',
    'bundle'      => 'booking_page',
    'label'       => 'Success Image',
  ])->save();
  $em->clearCachedFieldDefinitions();
}

\Drupal::service('entity_display.repository')
  ->getFormDisplay('node', 'booking_page', 'default')
  ->setComponent('field_success_image', ['type' => 'image_image', 'weight' => 70])
  ->save();

echo "Done: field_success_image\n";
