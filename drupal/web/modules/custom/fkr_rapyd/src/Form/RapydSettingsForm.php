<?php

namespace Drupal\fkr_rapyd\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class RapydSettingsForm extends ConfigFormBase {

  protected function getEditableConfigNames(): array {
    return ['fkr_rapyd.settings'];
  }

  public function getFormId(): string {
    return 'fkr_rapyd_settings';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('fkr_rapyd.settings');

    $form['access_key'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Access Key'),
      '#default_value' => $config->get('access_key'),
    ];

    $form['secret_key'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Secret Key'),
      '#default_value' => $config->get('secret_key'),
    ];

    $form['sandbox'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Sandbox mode'),
      '#default_value' => $config->get('sandbox'),
    ];

    $form['frontend_url'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Frontend URL'),
      '#default_value' => $config->get('frontend_url'),
    ];

    $form['webhook_url'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Webhook base URL'),
      '#default_value' => $config->get('webhook_url'),
    ];

    $form['email_subject'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Gift card email subject'),
      '#default_value' => $config->get('email_subject'),
    ];

    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('fkr_rapyd.settings')
      ->set('access_key',    $form_state->getValue('access_key'))
      ->set('secret_key',    $form_state->getValue('secret_key'))
      ->set('sandbox',       $form_state->getValue('sandbox'))
      ->set('frontend_url',  $form_state->getValue('frontend_url'))
      ->set('webhook_url',   $form_state->getValue('webhook_url'))
      ->set('email_subject', $form_state->getValue('email_subject'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
