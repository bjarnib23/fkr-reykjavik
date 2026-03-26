<?php

namespace Drupal\commerce_valitor\Controller;

use Drupal\commerce_payment\Entity\PaymentGatewayInterface;
use Drupal\commerce_valitor\Ajax\WindowCloseCommand;
use Drupal\commerce_valitor\Ajax\WindowOpenCommand;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The ValitorPay controller.
 *
 * @package Drupal\commerce_valitor\Controller
 */
class ValitorPay extends ControllerBase {

  /**
   * Convert Camel Case String to underscore-separated.
   *
   * @param string $str
   *   The input string.
   * @param string $separator
   *   Separator, the default is underscore.
   *
   * @return string
   *   The converted string.
   */
  private function camelCase2UnderScore($str, $separator = "_") {
    if (empty($str)) {
      return $str;
    }
    $str = lcfirst($str);
    $str = preg_replace("/[A-Z]/", $separator . "$0", $str);
    return strtolower($str);
  }

  /**
   * Webhook for 3DS authorization.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response object.
   */
  public function webhook(Request $request) {
    // Respond to health check request.
    if ($request->getMethod() == 'OPTIONS') {
      return new Response();
    }
    $verification_params = [];
    $params = $request->request->all();
    if (!isset($params['mdStatus']) || !in_array($params['mdStatus'], [1, 2, 4])) {
      $order_id = 'N/A';
      if (!empty($params['MD'])) {
        // Get the order id from MD.
        $order_id = base64_decode($params['MD']);
      }
      $this->getLogger('commerce_valitor')->error('Order ID: @order_id. @message',
        [
          '@order_id' => $order_id,
          '@message' => $params['mdErrorMsg'],
        ]);
      if (!empty($params['iReqCode']) && !empty($params['iReqDetail'])) {
        $this->getLogger('commerce_valitor')->error('Order ID: @order_id. Error code: @iReqCode, error message: @iReqDetail',
          [
            '@order_id' => $order_id,
            '@iReqCode' => $params['iReqCode'],
            '@iReqDetail' => $params['iReqDetail'],
          ]);
      }
      $message = $this->t('There was a problem validating your card, please contact us by email or phone.');
      $this->messenger()->addError($message);
    }
    else {
      foreach ($params as $key => $param) {
        $clean_key = str_replace('TDS2_', '', $key);
        if ($clean_key == 'dsTransID') {
          $clean_key = 'dsTransId';
        }
        if (in_array($clean_key, ['cavv', 'mdStatus', 'xid', 'dsTransId', 'eci'])) {
          $input_key = $this->camelCase2UnderScore($clean_key);
          $verification_params['#valitor-' . $input_key] = $param;
        }
      }
      $message = $this->t('Validation was successful');
    }
    return [
      '#theme' => 'valitor_card_verification',
      '#message' => $message,
      '#attached' => [
        'drupalSettings' => [
          'valitor' => $verification_params,
        ],
      ],
    ];
  }

  /**
   * Initialize 3DS security check.
   *
   * @param \Drupal\commerce_payment\Entity\PaymentGatewayInterface $commerce_payment_gateway
   *   The Commerce payment gateway interface.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object that the AJAX is responding to.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An Ajax response.
   */
  public function createVirtualCardWithVerification(PaymentGatewayInterface $commerce_payment_gateway, Request $request) {
    $response = new AjaxResponse();
    $params = $request->request->all();
    /** @var \Drupal\commerce_valitor\Plugin\Commerce\PaymentGateway\ValitorInterface $gateway_plugin */
    $gateway_plugin = $commerce_payment_gateway->getPlugin();
    $result = $gateway_plugin->verifyCard($params['cardNumber'], $params['expirationMonth'], $params['expirationYear'], $params['cvc'], $params['order_id'], $params['order_total_amount']);
    if (!empty($result['isSuccess'])) {
      $response->addCommand(new WindowOpenCommand($result['cardVerificationRawResponse'], [
        'width' => 700,
        'height' => 600,
      ]));
    }
    else {
      $message = $this->t('There was a problem validating your card, please contact us by email or phone.');
      if (!empty($result['errors'])) {
        $i = 0;
        foreach ($result['errors'] as $errors) {
          foreach ($errors as $error) {
            $response->addCommand(new MessageCommand($error, '.valitor-card-form', ['type' => 'error'], $i == 0));
            $i++;
          }
        }
      }
      else {
        $response->addCommand(new MessageCommand($message, '.valitor-card-form', ['type' => 'error']));
      }
      $response->addCommand(new InvokeCommand('.valitor-3ds-input', 'val', ['']));
      $response->addCommand(new WindowCloseCommand());
    }
    return $response;
  }

  /**
   * Dummy page for redirect to 3DS security check.
   *
   * @return array
   *   Render array for the page.
   */
  public function redirect3ds() {
    return [
      '#theme' => 'valitor_redirect3ds',
      '#message' => '',
    ];
  }

}
