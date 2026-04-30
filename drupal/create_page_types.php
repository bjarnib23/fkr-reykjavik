<?php

/**
 * Creates 5 focused content types to replace the monolithic page_content type.
 * Run with: ddev drush php:script ../create_page_types.php
 */

use Drupal\node\Entity\NodeType;
use Drupal\field\Entity\FieldConfig;
use Drupal\Core\Entity\Entity\EntityFormDisplay;

// ---------------------------------------------------------------------------
// Helper: attach an existing field storage to a bundle
// ---------------------------------------------------------------------------
function attach_field(string $field_name, string $bundle, string $label, array $extra = []): void {
  if (FieldConfig::loadByName('node', $bundle, $field_name)) {
    echo "  [skip] $field_name already on $bundle\n";
    return;
  }
  FieldConfig::create([
    'field_name'  => $field_name,
    'entity_type' => 'node',
    'bundle'      => $bundle,
    'label'       => $label,
  ] + $extra)->save();
  echo "  [ok] $field_name\n";
}

// ---------------------------------------------------------------------------
// Helper: create a content type if it doesn't exist
// ---------------------------------------------------------------------------
function create_type(string $type, string $name, string $description): void {
  if (NodeType::load($type)) {
    echo "[skip] Content type $type already exists\n";
    return;
  }
  NodeType::create([
    'type'        => $type,
    'name'        => $name,
    'description' => $description,
    'new_revision' => TRUE,
    'display_submitted' => FALSE,
  ])->save();
  echo "[created] $type\n";
}

// ---------------------------------------------------------------------------
// Shared nav fields (used by all types)
// ---------------------------------------------------------------------------
$nav_fields = [
  'field_slug'        => 'Slug',
  'field_nav_label'   => 'Nav Label',
  'field_nav_weight'  => 'Nav Weight',
  'field_nav_path'    => 'Nav Path',
  'field_nav_is_cta'  => 'Nav Is CTA',
];

// ---------------------------------------------------------------------------
// 1. basic_page — home, process, giftcard_thankyou, giftcard_cancel
// ---------------------------------------------------------------------------
create_type('basic_page', 'Basic Page', 'Simple pages: home, process, gift card thank you/cancel.');

$basic_fields = $nav_fields + [
  'field_page_subtitle' => 'Subtitle',
  'field_body_text'     => 'Body Text',
  'field_page_image'    => 'Image',
  'field_cta_text'      => 'CTA Button Text',
  'field_primary_button'=> 'Primary Button',
];

echo "Attaching fields to basic_page:\n";
foreach ($basic_fields as $name => $label) {
  attach_field($name, 'basic_page', $label);
}

// ---------------------------------------------------------------------------
// 2. booking_page — booking_step1 through booking_step4
// ---------------------------------------------------------------------------
create_type('booking_page', 'Booking Page', 'One node per booking step (steps 1–4).');

$booking_fields = $nav_fields + [
  'field_page_subtitle'     => 'Subtitle',
  'field_primary_button'    => 'Primary Button',
  'field_secondary_button'  => 'Secondary Button',
  'field_label_name'        => 'Label: Name',
  'field_label_phone'       => 'Phone Label',
  'field_label_email'       => 'Email Label',
  'field_label_service'     => 'Label: Service',
  'field_label_date'        => 'Label: Date',
  'field_label_time'        => 'Label: Time',
  'field_label_notes'       => 'Label: Notes',
  'field_placeholder_name'  => 'Placeholder: Name',
  'field_placeholder_phone' => 'Placeholder: Phone',
  'field_placeholder_email' => 'Placeholder: Email',
  'field_placeholder_notes' => 'Placeholder: Notes',
  'field_label_slots'       => 'Available Slots Label',
  'field_label_no_slots'    => 'No Slots Message',
  'field_success_heading'   => 'Success Heading',
  'field_success_body'      => 'Success Body',
];

echo "Attaching fields to booking_page:\n";
foreach ($booking_fields as $name => $label) {
  attach_field($name, 'booking_page', $label);
}

// ---------------------------------------------------------------------------
// 3. giftcard_page — the gift card purchase page
// ---------------------------------------------------------------------------
create_type('giftcard_page', 'Gift Card Page', 'The gift card purchase page.');

$giftcard_fields = $nav_fields + [
  'field_page_subtitle'      => 'Subtitle',
  'field_body_text'          => 'Body Text',
  'field_cta_text'           => 'CTA Button Text',
  'field_label_name'         => 'Label: Name',
  'field_label_phone'        => 'Phone Label',
  'field_label_email'        => 'Email Label',
  'field_label_confirm_email'=> 'Label: Confirm Email',
  'field_label_choose_amount'=> 'Label: Choose Amount',
  'field_label_your_details' => 'Label: Your Details',
  'field_label_loading'      => 'Label: Loading',
  'field_label_preview'      => 'Label: Preview',
  'field_card_title'         => 'Card: Title',
  'field_card_code_label'    => 'Card: Code Label',
  'field_card_no_expiry'     => 'Card: No Expiry',
  'field_card_note'          => 'Card: Note',
  'field_err_select_amount'  => 'Error: Select Amount',
  'field_err_name_required'  => 'Error: Name Required',
  'field_err_phone_required' => 'Error: Phone Required',
  'field_err_email_required' => 'Error: Email Required',
  'field_err_invalid_email'  => 'Error: Invalid Email',
  'field_err_email_mismatch' => 'Error: Email Mismatch',
  'field_err_load_amounts'   => 'Error: Load Amounts',
  'field_err_submit'         => 'Error: Submit',
  'field_err_payment'        => 'Error: Payment',
];

echo "Attaching fields to giftcard_page:\n";
foreach ($giftcard_fields as $name => $label) {
  attach_field($name, 'giftcard_page', $label);
}

// ---------------------------------------------------------------------------
// 4. pricelist_page — the price list page
// ---------------------------------------------------------------------------
create_type('pricelist_page', 'Price List Page', 'The price list page.');

$pricelist_fields = $nav_fields + [
  'field_body_text'  => 'Body Text',
  'field_page_image' => 'Image',
];

echo "Attaching fields to pricelist_page:\n";
foreach ($pricelist_fields as $name => $label) {
  attach_field($name, 'pricelist_page', $label);
}

// ---------------------------------------------------------------------------
// 5. faq_page — the FAQ page
// ---------------------------------------------------------------------------
create_type('faq_page', 'FAQ Page', 'The FAQ page.');

$faq_fields = $nav_fields + [
  'field_page_subtitle' => 'Subtitle',
];

echo "Attaching fields to faq_page:\n";
foreach ($faq_fields as $name => $label) {
  attach_field($name, 'faq_page', $label);
}

echo "\nDone. Run: ddev drush config:export\n";
