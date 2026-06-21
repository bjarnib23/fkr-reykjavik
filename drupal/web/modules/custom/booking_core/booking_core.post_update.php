<?php

/**
 * @file
 * Post-update functions for the Booking Core module.
 */

/**
 * Initialize all booking_core.settings config defaults on existing installs.
 */
function booking_core_post_update_settings_defaults(): void {
  $defaults = [
    'admin_email'     => '',
    'services'        => [],
    'open_days'       => [1, 2, 3, 4, 5],
    'open_time'       => '09:00',
    'close_time'      => '17:00',
    'slot_duration'   => 30,
    'weeks_ahead'     => 4,
    'flood_limit'     => 5,
    'flood_window'    => 3600,
    'blocked_periods' => [],
  ];

  $config = \Drupal::configFactory()->getEditable('booking_core.settings');
  $changed = FALSE;

  foreach ($defaults as $key => $value) {
    if ($config->get($key) === NULL) {
      $config->set($key, $value);
      $changed = TRUE;
    }
  }

  if ($changed) {
    $config->save();
  }
}
