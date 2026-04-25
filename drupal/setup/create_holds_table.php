<?php
// Run this script once to create the fkr_slot_holds table.
// Usage: ddev exec drush php:script setup/create_holds_table.php
$schema = \Drupal::database()->schema();
if (!$schema->tableExists('fkr_slot_holds')) {
  $schema->createTable('fkr_slot_holds', [
    'fields' => [
      'id'       => ['type' => 'serial', 'unsigned' => TRUE, 'not null' => TRUE],
      'datetime' => ['type' => 'varchar', 'length' => 20, 'not null' => TRUE],
      'token'    => ['type' => 'varchar', 'length' => 64, 'not null' => TRUE],
      'expires'  => ['type' => 'int', 'unsigned' => TRUE, 'not null' => TRUE],
    ],
    'primary key' => ['id'],
  ]);
  echo "Table created.\n";
} else {
  echo "Table already exists.\n";
}
