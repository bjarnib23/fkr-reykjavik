<?php
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;

$em = \Drupal::service('entity_field.manager');

$fields = [
  'field_footer_studio_label'  => 'Footer: Studio Label',
  'field_footer_contact_label' => 'Footer: Contact Label',
];

foreach ($fields as $name => $label) {
  if (!FieldStorageConfig::loadByName('node', $name)) {
    FieldStorageConfig::create([
      'field_name'  => $name,
      'entity_type' => 'node',
      'type'        => 'string',
    ])->save();
    $em->clearCachedFieldDefinitions();
  }

  if (!FieldConfig::loadByName('node', 'site_settings', $name)) {
    FieldConfig::create([
      'field_name'  => $name,
      'entity_type' => 'node',
      'bundle'      => 'site_settings',
      'label'       => $label,
    ])->save();
    $em->clearCachedFieldDefinitions();
  }

  echo "Done: $name\n";
}
