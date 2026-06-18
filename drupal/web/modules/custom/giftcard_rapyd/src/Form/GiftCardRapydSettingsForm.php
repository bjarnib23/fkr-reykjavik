<?php

namespace Drupal\giftcard_rapyd\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Settings form for the Rapyd payment gateway sub-module.
 */
class GiftCardRapydSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'giftcard_rapyd_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['giftcard_rapyd.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('giftcard_rapyd.settings');

    $form['rapyd_access_key_id'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Access key (Key module key ID)'),
      '#description'   => $this->t('The machine name of the Key entity that holds the Rapyd access key.'),
      '#default_value' => $config->get('rapyd_access_key_id'),
    ];

    $form['rapyd_secret_key_id'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Secret key (Key module key ID)'),
      '#description'   => $this->t('The machine name of the Key entity that holds the Rapyd secret key.'),
      '#default_value' => $config->get('rapyd_secret_key_id'),
    ];

    $form['rapyd_sandbox'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Use Rapyd sandbox environment'),
      '#default_value' => $config->get('rapyd_sandbox'),
    ];

    $form['rapyd_country'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Country code'),
      '#description'   => $this->t('ISO 3166-1 alpha-2 country code sent to Rapyd (e.g. IS, US, DE).'),
      '#default_value' => $config->get('rapyd_country'),
      '#size'          => 4,
      '#maxlength'     => 2,
      '#required'      => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('giftcard_rapyd.settings')
      ->set('rapyd_access_key_id', trim($form_state->getValue('rapyd_access_key_id')))
      ->set('rapyd_secret_key_id', trim($form_state->getValue('rapyd_secret_key_id')))
      ->set('rapyd_sandbox', (bool) $form_state->getValue('rapyd_sandbox'))
      ->set('rapyd_country', strtoupper(trim($form_state->getValue('rapyd_country'))))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
