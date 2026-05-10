<?php
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;

$em = \Drupal::service('entity_field.manager');

$fields = [
  'field_label_wishes'       => 'Wishes: Textarea Label',
  'field_placeholder_wishes' => 'Wishes: Textarea Placeholder',
  'field_summary_wishes'     => 'Wishes: Summary Label',
];

$weight = 60;
foreach ($fields as $name => $label) {
  if (!FieldStorageConfig::loadByName('node', $name)) {
    FieldStorageConfig::create([
      'field_name'  => $name,
      'entity_type' => 'node',
      'type'        => 'string',
    ])->save();
    $em->clearCachedFieldDefinitions();
  }

  if (!FieldConfig::loadByName('node', 'booking_page', $name)) {
    FieldConfig::create([
      'field_name'  => $name,
      'entity_type' => 'node',
      'bundle'      => 'booking_page',
      'label'       => $label,
    ])->save();
    $em->clearCachedFieldDefinitions();
  }

  \Drupal::service('entity_display.repository')
    ->getFormDisplay('node', 'booking_page', 'default')
    ->setComponent($name, ['type' => 'string_textfield', 'weight' => $weight++])
    ->save();

  echo "Done: $name\n";
}
