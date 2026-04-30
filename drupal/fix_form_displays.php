<?php

/**
 * Configures form displays for all new page content types.
 * Run with: ddev drush php:script ../fix_form_displays.php
 */

use Drupal\Core\Entity\Entity\EntityFormDisplay;

function show_fields(string $bundle, array $fields): void {
  $display = EntityFormDisplay::load("node.$bundle.default");
  if (!$display) {
    $display = EntityFormDisplay::create([
      'targetEntityType' => 'node',
      'bundle'           => $bundle,
      'mode'             => 'default',
      'status'           => TRUE,
    ]);
  }

  $weight = 10;
  foreach ($fields as $field_name => $widget) {
    $display->setComponent($field_name, [
      'type'   => $widget['type'],
      'weight' => $weight,
      'settings' => $widget['settings'] ?? [],
    ]);
    $weight += 10;
  }

  $display->save();
  echo "[ok] form display for $bundle\n";
}

$text   = ['type' => 'string_textfield', 'settings' => ['size' => 60]];
$area   = ['type' => 'text_textarea',    'settings' => ['rows' => 4]];
$image  = ['type' => 'image_image',      'settings' => ['preview_image_style' => 'thumbnail']];
$bool   = ['type' => 'boolean_checkbox', 'settings' => ['display_label' => TRUE]];
$number = ['type' => 'number'];

// basic_page
show_fields('basic_page', [
  'field_slug'          => $text,
  'field_page_subtitle' => $text,
  'field_body_text'     => $area,
  'field_cta_text'      => $text,
  'field_primary_button'=> $text,
  'field_page_image'    => $image,
  'field_nav_label'     => $text,
  'field_nav_weight'    => $number,
  'field_nav_path'      => $text,
  'field_nav_is_cta'    => $bool,
]);

// booking_page
show_fields('booking_page', [
  'field_slug'              => $text,
  'field_page_subtitle'     => $text,
  'field_primary_button'    => $text,
  'field_secondary_button'  => $text,
  'field_label_name'        => $text,
  'field_label_phone'       => $text,
  'field_label_email'       => $text,
  'field_label_service'     => $text,
  'field_label_date'        => $text,
  'field_label_time'        => $text,
  'field_label_notes'       => $text,
  'field_placeholder_name'  => $text,
  'field_placeholder_phone' => $text,
  'field_placeholder_email' => $text,
  'field_placeholder_notes' => $text,
  'field_label_slots'       => $text,
  'field_label_no_slots'    => $text,
  'field_success_heading'   => $text,
  'field_success_body'      => $text,
  'field_nav_label'         => $text,
  'field_nav_weight'        => $number,
  'field_nav_path'          => $text,
  'field_nav_is_cta'        => $bool,
]);

// giftcard_page
show_fields('giftcard_page', [
  'field_slug'               => $text,
  'field_page_subtitle'      => $text,
  'field_body_text'          => $area,
  'field_cta_text'           => $text,
  'field_label_name'         => $text,
  'field_label_phone'        => $text,
  'field_label_email'        => $text,
  'field_label_confirm_email'=> $text,
  'field_label_choose_amount'=> $text,
  'field_label_your_details' => $text,
  'field_label_loading'      => $text,
  'field_label_preview'      => $text,
  'field_card_title'         => $text,
  'field_card_code_label'    => $text,
  'field_card_no_expiry'     => $text,
  'field_card_note'          => $text,
  'field_err_select_amount'  => $text,
  'field_err_name_required'  => $text,
  'field_err_phone_required' => $text,
  'field_err_email_required' => $text,
  'field_err_invalid_email'  => $text,
  'field_err_email_mismatch' => $text,
  'field_err_load_amounts'   => $text,
  'field_err_submit'         => $text,
  'field_err_payment'        => $text,
  'field_nav_label'          => $text,
  'field_nav_weight'         => $number,
  'field_nav_path'           => $text,
  'field_nav_is_cta'         => $bool,
]);

// pricelist_page
show_fields('pricelist_page', [
  'field_slug'      => $text,
  'field_body_text' => $area,
  'field_page_image'=> $image,
  'field_nav_label' => $text,
  'field_nav_weight'=> $number,
  'field_nav_path'  => $text,
  'field_nav_is_cta'=> $bool,
]);

// faq_page
show_fields('faq_page', [
  'field_slug'          => $text,
  'field_page_subtitle' => $text,
  'field_nav_label'     => $text,
  'field_nav_weight'    => $number,
  'field_nav_path'      => $text,
  'field_nav_is_cta'    => $bool,
]);

echo "\nDone. Refresh the Drupal admin page.\n";
