<?php

use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;
use Drupal\node\Entity\NodeType;

// Create lookbook_page content type
if (!NodeType::load('lookbook_page')) {
  NodeType::create([
    'type'  => 'lookbook_page',
    'name'  => 'Lookbook Page',
  ])->save();
  echo "Created content type: lookbook_page\n";
}

// Create image field storage
if (!FieldStorageConfig::loadByName('node', 'field_lookbook_images')) {
  FieldStorageConfig::create([
    'field_name'   => 'field_lookbook_images',
    'entity_type'  => 'node',
    'type'         => 'image',
    'cardinality'  => -1,
  ])->save();
  echo "Created field storage: field_lookbook_images\n";
}

// Attach to lookbook_page
if (!FieldConfig::loadByName('node', 'lookbook_page', 'field_lookbook_images')) {
  FieldConfig::create([
    'field_name'  => 'field_lookbook_images',
    'entity_type' => 'node',
    'bundle'      => 'lookbook_page',
    'label'       => 'Images',
    'required'    => FALSE,
  ])->save();
  echo "Attached field to lookbook_page\n";
}

echo "Done\n";
