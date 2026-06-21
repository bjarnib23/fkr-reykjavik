<?php
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

$em = \Drupal::service('entity_field.manager');

$fields = ['field_footer_hours_label', 'field_footer_hours'];

foreach ($fields as $name) {
  $config = FieldConfig::loadByName('node', 'site_settings', $name);
  if ($config) {
    $config->delete();
    $em->clearCachedFieldDefinitions();
    echo "Deleted field config: $name\n";
  }

  $storage = FieldStorageConfig::loadByName('node', $name);
  if ($storage) {
    $storage->delete();
    $em->clearCachedFieldDefinitions();
    echo "Deleted field storage: $name\n";
  }
}
