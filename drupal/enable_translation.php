<?php
$types = ['basic_page', 'booking_page', 'giftcard_page', 'pricelist_page', 'faq_page', 'site_settings'];
foreach ($types as $type) {
  $settings = \Drupal\language\Entity\ContentLanguageSettings::loadByEntityTypeBundle('node', $type);
  if (!$settings) {
    $settings = \Drupal\language\Entity\ContentLanguageSettings::create([
      'target_entity_type_id' => 'node',
      'target_bundle' => $type,
    ]);
  }
  $settings->setLanguageAlterable(TRUE);
  $settings->setDefaultLangcode('is');
  $settings->setThirdPartySetting('content_translation', 'enabled', TRUE);
  $settings->save();
  echo "Done: $type\n";
}
\Drupal::service('router.builder')->rebuild();
echo "All done\n";
