<?php

namespace Drupal\commerce_valitor\PluginForm;

use Drupal\commerce_payment\PluginForm\PaymentMethodAddForm as BasePaymentMethodAddForm;
use Drupal\commerce_valitor\Plugin\Commerce\PaymentGateway\ValitorInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Class PaymentMethodAddForm adds Valitor elements to payment form.
 */
class PaymentMethodAddForm extends BasePaymentMethodAddForm {

  /**
   * {@inheritdoc}
   *
   * @see https://uat.valitorpay.com/index.html#operation/CreateVirtualCard
   */
  public function buildCreditCardForm(array $element, FormStateInterface $form_state) {
    $element = parent::buildCreditCardForm($element, $form_state);
    $order_id = FALSE;
    $order_total_amount = 0;
    // Try to get order_id from the form_state.
    $build_info = $form_state->getBuildInfo();
    // Check if we are on checkout page.
    if (!empty($build_info['callback_object']) && method_exists($build_info['callback_object'], 'getOrder')) {
      /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
      $order = $build_info['callback_object']->getOrder();
      $order_id = $order->id();
      $payment_gateway = $this->entity->getPaymentGateway();
      if ($payment_gateway instanceof ValitorInterface && $order->getTotalPrice()) {
        $order_total_amount = $payment_gateway->getMinorUnits($order->getTotalPrice());
      }
    }
    // Decorate credit card fields with classes for JS integration.
    if (!empty($element['number'])) {
      $element['number']['#attributes']['class'][] = 'valitor-card-number';
    }
    if (!empty($element['expiration'])) {
      $element['expiration']['month']['#attributes']['class'][] = 'valitor-card-expiration-month';
      $element['expiration']['year']['#attributes']['class'][] = 'valitor-card-expiration-year';
    }
    if (!empty($element['security_code'])) {
      $element['security_code']['#attributes']['class'][] = 'valitor-card-code';
    }
    // Populated by the JS library in response to 3DS security attempt.
    $element['cavv'] = [
      '#type' => 'hidden',
      '#attributes' => [
        'id' => 'valitor-cavv',
        'class' => 'valitor-3ds-input',
      ],
    ];
    $element['mdStatus'] = [
      '#type' => 'hidden',
      '#attributes' => [
        'id' => 'valitor-md_status',
        'class' => 'valitor-3ds-input',
      ],
    ];
    $element['xid'] = [
      '#type' => 'hidden',
      '#attributes' => [
        'id' => 'valitor-xid',
        'class' => 'valitor-3ds-input',
      ],
    ];
    $element['dsTransId'] = [
      '#type' => 'hidden',
      '#attributes' => [
        'id' => 'valitor-ds_trans_id',
        'class' => 'valitor-3ds-input',
      ],
    ];
    $element['eci'] = [
      '#type' => 'hidden',
      '#attributes' => [
        'id' => 'valitor-eci',
        'class' => 'valitor-3ds-input',
      ],
    ];
    $element['#suffix'] = '<div class="card-verification-warning">' . $this->t('Card verification is in progress in popup window that requires your attention. Please do not close this page.') . '</div>';
    // Alter the form with Valitor specific needs.
    $element['#attributes']['class'][] = 'valitor-card-form';
    $element['#attached']['library'][] = 'commerce_valitor/form';
    $element['#attached']['library'][] = 'core/drupal.dialog.ajax';
    $element['#attached']['drupalSettings']['commerceValitor'] = [
      'order_id' => $order_id,
      'order_total_amount' => $order_total_amount,
      'verify_url' => Url::fromRoute('commerce_valitor.create_payment_method', ['commerce_payment_gateway' => $this->entity->getPaymentGatewayId()])->setAbsolute()->toString(),
      'popup_url' => Url::fromRoute('commerce_valitor.redirect_3ds')->setAbsolute()->toString(),
    ];

    return $element;
  }

}
