<?php

namespace Drupal\commerce_valitor\PluginForm;

use Drupal\commerce_payment\CreditCard;
use Drupal\commerce_payment\Exception\DeclineException;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\PluginForm\PaymentMethodEditForm as CommercePaymentMethodEditForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * The payment method edit form plugin.
 */
class PaymentMethodEditForm extends CommercePaymentMethodEditForm {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method */
    $payment_method = $this->entity;
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['#attached']['library'][] = 'commerce_payment/payment_method_form';
    $form['payment_details']['number_readonly'] = [
      '#markup' => '<div class="description">' . $this->t('Card number and security code can not be changed. Please create a new payment method if you need to use another card.') . '</div>',
      '#weight' => -100,
    ];

    $form['payment_details']['number']['#default_value'] = $payment_method->card_number->value;
    $form['payment_details']['number']['#access'] = FALSE;
    $form['payment_details']['security_code']['#default_value'] = 000;
    $form['payment_details']['security_code']['#access'] = FALSE;

    $form['billing_information']['#access'] = FALSE;

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method */
    $payment_method = $this->entity;

    $this->submitCreditCardForm($form['payment_details'], $form_state);

    /** @var \Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsUpdatingStoredPaymentMethodsInterface $payment_gateway_plugin */
    $payment_gateway_plugin = $this->plugin;
    // The payment method form is customer facing. For security reasons
    // the returned errors need to be more generic.
    try {
      $payment_gateway_plugin->updatePaymentMethod($payment_method);
    }
    catch (DeclineException $e) {
      \Drupal::logger('commerce_valitor')->warning($e->getMessage());
      throw new DeclineException('We encountered an error processing your payment method. Please verify your details and try again.');
    }
    catch (PaymentGatewayException $e) {
      \Drupal::logger('commerce_valitor')->error($e->getMessage());
      throw new PaymentGatewayException('We encountered an unexpected error processing your payment method. Please try again later.');
    }
  }

  /**
   * Validates the credit card form.
   *
   * @param array $element
   *   The credit card form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the complete form.
   */
  protected function validateCreditCardForm(array &$element, FormStateInterface $form_state) {
    $values = $form_state->getValue($element['#parents']);

    if (!CreditCard::validateExpirationDate($values['expiration']['month'], $values['expiration']['year'])) {
      $form_state->setError($element['expiration'], $this->t('You have entered an expired credit card.'));
    }
  }

  /**
   * Handles the submission of the credit card form.
   *
   * @param array $element
   *   The credit card form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the complete form.
   */
  protected function submitCreditCardForm(array $element, FormStateInterface $form_state) {
    $values = $form_state->getValue($element['#parents']);
    $this->entity->card_exp_month = $values['expiration']['month'];
    $this->entity->card_exp_year = $values['expiration']['year'];
    $expires = CreditCard::calculateExpirationTimestamp($values['expiration']['month'], $values['expiration']['year']);
    $this->entity->setExpiresTime($expires);
  }

}
