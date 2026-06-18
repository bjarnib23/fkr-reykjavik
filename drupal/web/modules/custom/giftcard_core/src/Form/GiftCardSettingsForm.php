<?php

namespace Drupal\giftcard_core\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Settings form for the Gift Card Core module.
 */
class GiftCardSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'giftcard_core_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['giftcard_core.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('giftcard_core.settings');

    $form['currency'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Currency code'),
      '#description'   => $this->t('ISO 4217 currency code for gift card amounts (e.g. ISK, EUR, USD).'),
      '#default_value' => $config->get('currency'),
      '#size'          => 5,
      '#maxlength'     => 3,
      '#required'      => TRUE,
    ];

    $form['min_amount'] = [
      '#type'          => 'number',
      '#title'         => $this->t('Minimum gift card amount'),
      '#description'   => $this->t('Smallest purchase amount allowed, in whole units of the configured currency (e.g. 1000 = 1000 ISK).'),
      '#default_value' => $config->get('min_amount'),
      '#min'           => 1,
      '#required'      => TRUE,
    ];

    $form['flood_threshold'] = [
      '#type'          => 'number',
      '#title'         => $this->t('Max checkout attempts per hour per IP'),
      '#description'   => $this->t('Requests beyond this limit are blocked to prevent abuse.'),
      '#default_value' => $config->get('flood_threshold'),
      '#min'           => 1,
      '#max'           => 100,
    ];

    $amounts = $config->get('predefined_amounts') ?: [];
    $form['predefined_amounts'] = [
      '#type'          => 'textarea',
      '#title'         => $this->t('Predefined gift card amounts'),
      '#description'   => $this->t('One integer amount per line (e.g. 5000). Used by the React checkout API.'),
      '#default_value' => implode("\n", $amounts),
      '#rows'          => 6,
    ];

    $form['complete_url'] = [
      '#type'          => 'url',
      '#title'         => $this->t('Payment complete URL'),
      '#description'   => $this->t('Where Rapyd redirects the buyer after a successful payment (e.g. https://fkr.is/giftcard/thank-you).'),
      '#default_value' => $config->get('complete_url'),
    ];

    $form['cancel_url'] = [
      '#type'          => 'url',
      '#title'         => $this->t('Payment cancel URL'),
      '#description'   => $this->t('Where Rapyd redirects the buyer if they cancel payment (e.g. https://fkr.is/giftcard/cancel).'),
      '#default_value' => $config->get('cancel_url'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $raw_amounts = array_filter(
      array_map('intval', explode("\n", $form_state->getValue('predefined_amounts'))),
      static fn(int $v): bool => $v > 0,
    );
    sort($raw_amounts);

    $this->config('giftcard_core.settings')
      ->set('currency', strtoupper(trim($form_state->getValue('currency'))))
      ->set('min_amount', (int) $form_state->getValue('min_amount'))
      ->set('flood_threshold', (int) $form_state->getValue('flood_threshold'))
      ->set('predefined_amounts', array_values($raw_amounts))
      ->set('complete_url', trim($form_state->getValue('complete_url')))
      ->set('cancel_url', trim($form_state->getValue('cancel_url')))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
