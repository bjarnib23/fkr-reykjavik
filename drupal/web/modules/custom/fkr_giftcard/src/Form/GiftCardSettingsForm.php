<?php

namespace Drupal\fkr_giftcard\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class GiftCardSettingsForm extends ConfigFormBase {

  protected function getEditableConfigNames(): array {
    return ['fkr_giftcard.settings'];
  }

  public function getFormId(): string {
    return 'fkr_giftcard_settings';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('fkr_giftcard.settings');

    $form['email_subject'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Gift card email subject'),
      '#default_value' => $config->get('email_subject'),
      '#required'      => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('fkr_giftcard.settings')
      ->set('email_subject', $form_state->getValue('email_subject'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
