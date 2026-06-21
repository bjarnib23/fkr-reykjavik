<?php

namespace Drupal\booking_core\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Settings form for the Booking Core module.
 */
class BookingSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'booking_core_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['booking_core.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config   = $this->config('booking_core.settings');
    $services = $config->get('services');
    $days     = $config->get('open_days');

    $form['company_name_notice'] = [
      '#type'   => 'item',
      '#markup' => $this->t('The company name used in emails is taken from the <a href="/admin/config/system/site-information">site name</a>.'),
    ];

    $form['admin_email'] = [
      '#type'          => 'email',
      '#title'         => $this->t('Admin notification email'),
      '#default_value' => $config->get('admin_email'),
    ];

    $form['services'] = [
      '#type'          => 'textarea',
      '#title'         => $this->t('Available services'),
      '#description'   => $this->t('One service per line.'),
      '#default_value' => implode("\n", $services),
      '#rows'          => 6,
    ];

    $form['hours'] = [
      '#type'  => 'fieldset',
      '#title' => $this->t('Business hours'),
    ];

    $form['hours']['open_days'] = [
      '#type'          => 'checkboxes',
      '#title'         => $this->t('Open days'),
      '#options'       => [
        1 => $this->t('Monday'),
        2 => $this->t('Tuesday'),
        3 => $this->t('Wednesday'),
        4 => $this->t('Thursday'),
        5 => $this->t('Friday'),
        6 => $this->t('Saturday'),
        0 => $this->t('Sunday'),
      ],
      '#default_value' => $days,
    ];

    $form['hours']['open_time'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Opening time'),
      '#description'   => $this->t('Format: HH:MM (e.g. 09:00)'),
      '#default_value' => $config->get('open_time'),
      '#size'          => 8,
    ];

    $form['hours']['close_time'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Closing time'),
      '#description'   => $this->t('Format: HH:MM (e.g. 17:00)'),
      '#default_value' => $config->get('close_time'),
      '#size'          => 8,
    ];

    $form['hours']['slot_duration'] = [
      '#type'          => 'select',
      '#title'         => $this->t('Slot duration'),
      '#options'       => [
        15  => $this->t('15 minutes'),
        30  => $this->t('30 minutes'),
        45  => $this->t('45 minutes'),
        60  => $this->t('1 hour'),
        90  => $this->t('1.5 hours'),
        120 => $this->t('2 hours'),
      ],
      '#default_value' => $config->get('slot_duration'),
    ];

    $form['hours']['weeks_ahead'] = [
      '#type'          => 'number',
      '#title'         => $this->t('Weeks ahead'),
      '#description'   => $this->t('How many weeks ahead customers can book.'),
      '#default_value' => $config->get('weeks_ahead'),
      '#min'           => 1,
      '#max'           => 52,
    ];

    $form['flood'] = [
      '#type'  => 'fieldset',
      '#title' => $this->t('Flood protection'),
    ];

    $form['flood']['flood_limit'] = [
      '#type'          => 'number',
      '#title'         => $this->t('Max booking attempts'),
      '#description'   => $this->t('Maximum number of booking submissions allowed per IP within the flood window.'),
      '#default_value' => $config->get('flood_limit'),
      '#min'           => 1,
      '#max'           => 100,
    ];

    $form['flood']['flood_window'] = [
      '#type'          => 'number',
      '#title'         => $this->t('Flood window (seconds)'),
      '#description'   => $this->t('Time window in seconds for the attempt limit. 3600 = 1 hour.'),
      '#default_value' => $config->get('flood_window'),
      '#min'           => 60,
      '#max'           => 86400,
    ];

    // ---- Blocked periods ----
    if ($form_state->get('blocked_periods') === NULL) {
      $form_state->set('blocked_periods', $config->get('blocked_periods'));
    }
    $periods = $form_state->get('blocked_periods');
    $removed = $form_state->get('blocked_removed') ?? [];

    $form['blocked'] = [
      '#type'       => 'fieldset',
      '#title'      => $this->t('Blocked dates / periods'),
      '#prefix'     => '<div id="blocked-periods-wrapper">',
      '#suffix'     => '</div>',
    ];

    $form['blocked']['table'] = [
      '#type'   => 'table',
      '#header' => [
        $this->t('Date'),
        $this->t('All day'),
        $this->t('Start time'),
        $this->t('End time'),
        $this->t('Reason'),
        $this->t('Remove'),
      ],
      '#empty'  => $this->t('No blocked periods. Click "Add period" to block a date.'),
    ];

    foreach ($periods as $i => $period) {
      if (in_array($i, $removed)) {
        continue;
      }
      $form['blocked']['table'][$i]['date'] = [
        '#type'          => 'date',
        '#default_value' => $period['date'] ?? '',
      ];
      $form['blocked']['table'][$i]['all_day'] = [
        '#type'          => 'checkbox',
        '#default_value' => $period['all_day'] ?? FALSE,
      ];
      $form['blocked']['table'][$i]['start_time'] = [
        '#type'          => 'textfield',
        '#default_value' => $period['start_time'] ?? '',
        '#size'          => 6,
        '#placeholder'   => 'HH:MM',
        '#states'        => [
          'disabled' => [':input[name="table[' . $i . '][all_day]"]' => ['checked' => TRUE]],
        ],
      ];
      $form['blocked']['table'][$i]['end_time'] = [
        '#type'          => 'textfield',
        '#default_value' => $period['end_time'] ?? '',
        '#size'          => 6,
        '#placeholder'   => 'HH:MM',
        '#states'        => [
          'disabled' => [':input[name="table[' . $i . '][all_day]"]' => ['checked' => TRUE]],
        ],
      ];
      $form['blocked']['table'][$i]['reason'] = [
        '#type'          => 'textfield',
        '#default_value' => $period['reason'] ?? '',
        '#size'          => 20,
      ];
      $form['blocked']['table'][$i]['remove'] = [
        '#type'                    => 'submit',
        '#value'                   => $this->t('Remove'),
        '#name'                    => 'remove_period_' . $i,
        '#submit'                  => ['::removeBlockedPeriod'],
        '#limit_validation_errors' => [],
        '#ajax'                    => [
          'callback' => '::blockedPeriodsCallback',
          'wrapper'  => 'blocked-periods-wrapper',
        ],
        '#row'                     => $i,
      ];
    }

    $form['blocked']['add_period'] = [
      '#type'                    => 'submit',
      '#value'                   => $this->t('Add period'),
      '#submit'                  => ['::addBlockedPeriod'],
      '#limit_validation_errors' => [],
      '#ajax'                    => [
        'callback' => '::blockedPeriodsCallback',
        'wrapper'  => 'blocked-periods-wrapper',
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * AJAX submit handler to add a new blocked period row.
   */
  public function addBlockedPeriod(array &$form, FormStateInterface $form_state): void {
    $periods   = $form_state->get('blocked_periods') ?? [];
    $periods[] = ['date' => '', 'all_day' => FALSE, 'start_time' => '', 'end_time' => '', 'reason' => ''];
    $form_state->set('blocked_periods', $periods);
    $form_state->setRebuild();
  }

  /**
   * AJAX submit handler to mark a blocked period row for removal.
   */
  public function removeBlockedPeriod(array &$form, FormStateInterface $form_state): void {
    $trigger   = $form_state->getTriggeringElement();
    $row       = $trigger['#row'];
    $removed   = $form_state->get('blocked_removed') ?? [];
    $removed[] = $row;
    $form_state->set('blocked_removed', $removed);
    $form_state->setRebuild();
  }

  /**
   * AJAX callback returning the updated blocked periods fieldset.
   */
  public function blockedPeriodsCallback(array &$form, FormStateInterface $form_state): array {
    return $form['blocked'];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    foreach (['open_time', 'close_time'] as $field) {
      $val = $form_state->getValue($field);
      if (!preg_match('/^\d{2}:\d{2}$/', $val)) {
        $form_state->setErrorByName($field, $this->t('Time must be in HH:MM format.'));
      }
    }

    $table   = $form_state->getValue('table') ?? [];
    $removed = $form_state->get('blocked_removed') ?? [];
    foreach ($table as $i => $row) {
      if (in_array($i, $removed)) {
        continue;
      }

      if (empty($row['date'])) {
        $form_state->setError($form['blocked']['table'][$i]['date'], $this->t('Date is required for each blocked period.'));
        continue;
      }

      $parsed = \DateTime::createFromFormat('Y-m-d', $row['date']);
      if (!$parsed || $parsed->format('Y-m-d') !== $row['date']) {
        $form_state->setError($form['blocked']['table'][$i]['date'], $this->t('The blocked period date is not valid.'));
        continue;
      }

      if ($parsed->format('Y-m-d') < (new \DateTime('today'))->format('Y-m-d')) {
        $form_state->setError($form['blocked']['table'][$i]['date'], $this->t('The blocked period date cannot be in the past.'));
      }

      if (empty($row['all_day'])) {
        foreach (['start_time', 'end_time'] as $tf) {
          if (!empty($row[$tf]) && !preg_match('/^\d{2}:\d{2}$/', $row[$tf])) {
            $form_state->setError($form['blocked']['table'][$i][$tf], $this->t('Time must be in HH:MM format.'));
          }
        }

        if (!empty($row['start_time']) && !empty($row['end_time']) && $row['start_time'] >= $row['end_time']) {
          $form_state->setError($form['blocked']['table'][$i]['start_time'], $this->t('Start time must be before end time.'));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $raw      = $form_state->getValue('services');
    $services = array_values(array_filter(array_map('trim', explode("\n", $raw))));
    $days     = array_values(array_map('intval', array_filter($form_state->getValue('open_days'))));

    $table   = $form_state->getValue('table') ?? [];
    $removed = $form_state->get('blocked_removed') ?? [];
    $blocked = [];
    foreach ($table as $i => $row) {
      if (in_array($i, $removed) || empty($row['date'])) {
        continue;
      }
      $blocked[] = [
        'date'       => $row['date'],
        'all_day'    => (bool) $row['all_day'],
        'start_time' => $row['all_day'] ? '' : ($row['start_time'] ?? ''),
        'end_time'   => $row['all_day'] ? '' : ($row['end_time'] ?? ''),
        'reason'     => trim($row['reason'] ?? ''),
      ];
    }

    $this->config('booking_core.settings')
      ->set('admin_email', $form_state->getValue('admin_email'))
      ->set('services', $services)
      ->set('open_days', $days)
      ->set('open_time', $form_state->getValue('open_time'))
      ->set('close_time', $form_state->getValue('close_time'))
      ->set('slot_duration', (int) $form_state->getValue('slot_duration'))
      ->set('weeks_ahead', (int) $form_state->getValue('weeks_ahead'))
      ->set('flood_limit', (int) $form_state->getValue('flood_limit'))
      ->set('flood_window', (int) $form_state->getValue('flood_window'))
      ->set('blocked_periods', $blocked)
      ->save();

    parent::submitForm($form, $form_state);
  }

}
