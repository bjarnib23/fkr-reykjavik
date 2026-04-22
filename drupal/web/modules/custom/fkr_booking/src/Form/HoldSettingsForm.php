<?php

namespace Drupal\fkr_booking\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class HoldSettingsForm extends ConfigFormBase {

  public function getFormId(): string {
    return 'fkr_booking_hold_settings';
  }

  protected function getEditableConfigNames(): array {
    return ['fkr_booking.settings'];
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('fkr_booking.settings');

    $form['hold_minutes'] = [
      '#type'          => 'number',
      '#title'         => $this->t('Slot hold duration (minutes)'),
      '#description'   => $this->t('How many minutes a slot is reserved while a customer fills in their details.'),
      '#default_value' => $config->get('hold_minutes') ?? 6,
      '#min'           => 1,
      '#max'           => 60,
      '#required'      => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('fkr_booking.settings')
      ->set('hold_minutes', (int) $form_state->getValue('hold_minutes'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
