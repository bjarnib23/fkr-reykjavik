<?php
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;

$em = \Drupal::service('entity_field.manager');

$fields = [
  'field_footer_nav_label'   => ['label' => 'Footer: Nav Label',           'type' => 'string'],
  'field_footer_hours_label' => ['label' => 'Footer: Hours Label',         'type' => 'string'],
  'field_footer_hours'       => ['label' => 'Footer: Opening Hours',       'type' => 'string_long'],
  'field_footer_phone_label' => ['label' => 'Footer: Phone Label',         'type' => 'string'],
  'field_footer_email_label' => ['label' => 'Footer: Email Label',         'type' => 'string'],
  'field_site_email'         => ['label' => 'Site Email',                  'type' => 'string'],
];

$weight = 30;
foreach ($fields as $name => $info) {
  if (!FieldStorageConfig::loadByName('node', $name)) {
    FieldStorageConfig::create([
      'field_name'  => $name,
      'entity_type' => 'node',
      'type'        => $info['type'],
    ])->save();
    $em->clearCachedFieldDefinitions();
  }

  if (!FieldConfig::loadByName('node', 'site_settings', $name)) {
    FieldConfig::create([
      'field_name'  => $name,
      'entity_type' => 'node',
      'bundle'      => 'site_settings',
      'label'       => $info['label'],
    ])->save();
    $em->clearCachedFieldDefinitions();
  }

  \Drupal::service('entity_display.repository')
    ->getFormDisplay('node', 'site_settings', 'default')
    ->setComponent($name, ['type' => $info['type'] === 'string_long' ? 'string_textarea' : 'string_textfield', 'weight' => $weight++])
    ->save();

  echo "Done: $name\n";
}
