<?php

namespace Drupal\commerce_valitor\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_payment\CreditCard;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_payment\Exception\HardDeclineException;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\Exception\SoftDeclineException;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OnsitePaymentGatewayBase;
use Drupal\commerce_price\Calculator;
use Drupal\commerce_price\Price;
use Drupal\commerce_valitor\ValitorPayApi;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the VALITOR payment gateway.
 *
 * @CommercePaymentGateway(
 *   id = "commerce_valitor",
 *   label = @Translation("VALITOR (On-site)"),
 *   display_label = @Translation("Pay with credit card"),
 *   forms = {
 *     "add-payment-method" = "Drupal\commerce_valitor\PluginForm\PaymentMethodAddForm",
 *     "edit-payment-method" = "Drupal\commerce_valitor\PluginForm\PaymentMethodEditForm",
 *     "refund-payment" = "Drupal\commerce_valitor\PluginForm\PaymentRefundForm"
 *   },
 *   payment_method_types = {"credit_card"},
 *   credit_card_types = {
 *     "amex",
 *     "dinersclub",
 *     "discover",
 *     "jcb",
 *     "mastercard",
 *     "visa",
 *   },
 * )
 */
class Valitor extends OnsitePaymentGatewayBase implements ValitorInterface {

  /**
   * Logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Drupal messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Http client service.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $client;

  /**
   * The module list.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected $moduleList;

  /**
   * The currency repository.
   *
   * @var \Drupal\commerce_price\Repository\CurrencyRepositoryInterface
   */
  protected $currencyRepository;

  /**
   * Currency fraction digits.
   *
   * @var array
   */
  private $currencyFractionDigits = [];

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->logger = $container->get('logger.channel.commerce_valitor');
    $instance->messenger = $container->get('messenger');
    $instance->client = $container->get('http_client');
    $instance->moduleList = $container->get('extension.list.module');
    $instance->currencyRepository = $container->get('commerce_price.currency_repository');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'api_key' => '',
      'contract_number' => '',
      'pos_id' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API key'),
      '#description' => $this->t("Merchant’s api key for ValitorPay."),
      '#default_value' => $this->configuration['api_key'],
      '#required' => TRUE,
    ];
    $form['contract_number'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Contract number'),
      '#description' => $this->t("Merchant’s contract number."),
      '#default_value' => $this->configuration['contract_number'],
    ];
    $form['pos_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('POS ID'),
      '#description' => $this->t('POS terminal ID received from Valitor.'),
      '#default_value' => $this->configuration['pos_id'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);
    $values = $form_state->getValue($form['#parents']);
    if (!empty($values['contract_number']) && !is_numeric($values['contract_number'])) {
      $form_state->setError($form['contract_number'], $this->t('Contract number must be numeric'));
    }
    if (!empty($values['pos_id']) && !is_numeric($values['pos_id'])) {
      $form_state->setError($form['pos_id'], $this->t('POS terminal id must be numeric'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      $this->configuration['api_key'] = $values['api_key'];
      $this->configuration['contract_number'] = $values['contract_number'];
      $this->configuration['pos_id'] = $values['pos_id'];
    }
  }

  /**
   * Gets module version.
   *
   * @return mixed|string
   *   The module version string at best.
   */
  private function getModuleVersion() {
    $module_info = $this->moduleList->get('commerce_valitor');
    if (!empty($module_info->info['version'])) {
      return $module_info->info['version'];
    }
    return '2.x-dev';
  }

  /**
   * Gets system calling name with version.
   *
   * @return string
   *   The versioned name string.
   */
  private function getSystemCalling() {
    return 'DrupalCommerceValitor v' . $this->getModuleVersion();
  }

  /**
   * {@inheritdoc}
   */
  public function getMinorUnits(Price $amount) {
    // Add fix for ISK currency, because it has to be 2 fraction digits.
    if ($amount->getCurrencyCode() == 'ISK') {
      $number = $amount->getNumber();
      $number = Calculator::multiply($number, pow(10, 2));
      return round($number);
    }
    // Use minor units converter service if available (commerce >= v2.25).
    if (isset($this->minorUnitsConverter)) {
      return $this->minorUnitsConverter->toMinorUnits($amount);
    }
    // Legacy minor units converter.
    if (!isset($this->currencyFractionDigits[$amount->getCurrencyCode()])) {
      $currency = $this->currencyRepository->get($amount->getCurrencyCode());
      $this->currencyFractionDigits[$amount->getCurrencyCode()] = $currency->getFractionDigits();
    }
    $number = $amount->getNumber();
    $number = Calculator::multiply($number, pow(10, $this->currencyFractionDigits[$amount->getCurrencyCode()]));
    return round($number);
  }

  /**
   * Throws the proper exception according to the response code.
   *
   * Valitor sends in response the status code of the error.
   *
   * @param string $message
   *   The description of the error.
   * @param array $response
   *   Server response.
   *
   * @see \Drupal\commerce_valitor\ValitorApi
   */
  private function throwException($message, array $response) {
    if (!empty($response['order_id'])) {
      $message = $this->t('Payment for @order_id failed.', ['@order_id' => $response['order_id']]) . ' ' . $message;
    }
    if (!empty($response['errors'])) {
      $this->logger->error($message);
      $this->messenger->addError($this->t('Virtual card number missing, please try deleting the credit card and try registering again'));
      throw new HardDeclineException($message);
    }

    $status_code = '';
    if (!empty($response['responseCode'])) {
      $status_code = substr($response['responseCode'], 0, 2);
    }

    // Status codes that are not allowed to retry to charge.
    // @see https://uat.valitorpay.com/ResponseCodes.html
    $hard_decline_codes = [
      '03', '04', '07', '12', '15', '41', '43', '57', '62', '65', '78', '93',
      'R0', 'R1', 'R3', '1A',
    ];

    // Throw the correct exception, so the charging can go into dunning
    // or the payment changes to failure status.
    if (in_array($status_code, $hard_decline_codes)) {
      $this->logger->warning($message);
      $this->logger->warning(Json::encode($response));
      $user_message = $this->t('An error occurred while performing payment, please contact us either by phone or email');
      $this->messenger->addError($user_message);
      throw new HardDeclineException($message, 400);
    }
    elseif (!empty($status_code)) {
      // All other statuses are allowed to be retried, at least 3 times.
      $this->logger->error(Json::encode($response));
      // Do not display correlation ID to the customer.
      $pos = strpos($message, 'Correlation ID');
      if ($pos !== FALSE) {
        $user_message = substr($message, 0, $pos - 1);
        $this->messenger->addError($user_message);
      }
      throw new SoftDeclineException($message, 400);
    }
    else {
      throw new PaymentGatewayException($message, 400);
    }
  }

  /**
   * Prepares error message.
   *
   * @param array $response
   *   Valitor payment gateway response.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|mixed
   *   An error message at best.
   */
  private function prepareErrorMessage(array $response) {
    $prefix = '';
    if (!empty($response['order_id'])) {
      $prefix = $this->t('Order: @id', ['@id' => $response['order_id'] . '. ']);
    }
    if (!empty($response['errors'])) {
      $errors = [];
      foreach ($response['errors'] as $error) {
        $errors[] = is_array($error) ? implode(', ', $error) : $error;
      }
      return $prefix . $this->t('@title: @errors', [
        '@title' => $response['title'],
        '@errors' => implode(', ', $errors),
      ]);
    }
    elseif (!empty($response['title'])) {
      return $prefix . $response['title'];
    }
    elseif (!empty($response['responseDescription']) && isset($response['isSuccess']) && !$response['isSuccess']) {
      return $prefix . $response['responseDescription'];
    }
    return $this->t('Unknown error occurred during request processing, please contact site administrators');
  }

  /**
   * Handles declines.
   *
   * @param array $response
   *   A response array.
   */
  protected function handleDecline(array $response): void {
    if (empty($response) || !empty($response['errors']) || (isset($response['isSuccess']) && !$response['isSuccess'])) {
      $message = $this->prepareErrorMessage($response);
      $this->throwException($message, $response);
    }
  }

  /**
   * Verifies the card with 3DS protocols.
   *
   * @param string $cardNumber
   *   The card number.
   * @param string $expirationMonth
   *   The card expiration month.
   * @param string $expirationYear
   *   The card expiration year.
   * @param string $cvc
   *   The card security code.
   * @param int|null $order_id
   *   The order id (optional).
   * @param int $order_total_amount
   *   The order total amount (optional).
   *
   * @return array|false|mixed
   *   The verification response.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function verifyCard($cardNumber, $expirationMonth, $expirationYear, $cvc, $order_id = NULL, $order_total_amount = 0) {
    $valitor_api = $this->initializeValitorApi();
    $currency = "ISK";
    /** @var \Drupal\commerce_store\Entity\StoreInterface $default_store */
    $default_store = $this->entityTypeManager->getStorage('commerce_store')->loadDefault();
    if ($default_store) {
      $currency = $default_store->getDefaultCurrencyCode();
    }
    $payload = [
      "MD" => $order_id ? base64_encode($order_id) : '',
      "amount" => $order_total_amount ?? 0,
      "currency" => $currency,
      "cardNumber" => $cardNumber,
      "expirationMonth" => $expirationMonth,
      "expirationYear" => $expirationYear,
      "cardholderDeviceType" => "WWW",
      "authenticationUrl" => Url::fromRoute('commerce_valitor.webhook')->setAbsolute(TRUE)->toString(),
      "systemCalling" => $this->getSystemCalling(),
    ];
    // @todo find out what to do here. German card didn't accept the exponent 2
    // with the following message:
    // [the exponent value is not as defined in ISO 4217 for the given currency
    // (purchaseExponent)] ISO code not valid per ISO tables (for either country
    // or currency) or code is one of the excluded values Error component: A
    // @see https://uat.valitorpay.com/index.html#operation/CardVerification (exponent property for more details)
    // if ($currency == 'ISK') { $payload['exponent'] = 2; }
    $response = $valitor_api->verifyCard($payload);
    if (isset($response['isSuccess']) && !$response['isSuccess']) {
      $this->logger->warning('Order ID: @order_id. Card Verification failed with following message: @message', [
        '@order_id' => $order_id ?: 'N/A',
        '@message' => $response['responseDescription'],
      ]);
    }

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function createPayment(PaymentInterface $payment, $capture = TRUE) {
    // Authorization is made when ::createPaymentMethod.
    $this->assertPaymentState($payment, ['new']);
    $payment_method = $payment->getPaymentMethod();
    $this->assertPaymentMethod($payment_method);
    // If not specified, capture the entire amount.
    $amount = $payment->getAmount();
    $valitor_api = $this->initializeValitorApi();
    if ($valitor_api->getEnvironment() != $payment_method->getPaymentGatewayMode()) {
      throw new HardDeclineException('Payment method gateway mode does not match the environment');
    }
    $payload = [
      'virtualCardNumber' => $payment_method->getRemoteId(),
      'virtualCardPaymentAdditionalData' => [
        'merchantReferenceData' => $payment->getOrderId(),
      ],
      'currency' => $amount->getCurrencyCode(),
      'amount' => $this->getMinorUnits($amount),
      'systemCalling' => $this->getSystemCalling(),
    ];
    if ($capture) {
      $payload += [
        'operation' => 'Sale',
      ];
    }
    else {
      $expireDate = DrupalDateTime::createFromTimestamp($this->time->getRequestTime());
      $expireDate = $expireDate->modify('+1 day');
      $payload += [
        'operation' => 'Auth',
        'authType' => 'Authorization',
        'delayedClearingData' => [
          'whenToClear' => $expireDate->format('Y-m-d'),
          'whenExpiredAction' => 'Reversal',
        ],
      ];
    }
    $response = $valitor_api->createVirtualCardPayment($payload);
    if ($response) {
      $response['order_id'] = $payment->getOrderId();
    }
    // Process response errors.
    $this->handleDecline($response);
    if ($capture) {
      $payment->setState('completed');
      $payment->setCompletedTime($this->time->getRequestTime());
    }
    else {
      $payment->setState('authorization');
    }
    $remote_id = $response['transactionID'] . '|' . $response['acquirerReferenceNumber'];
    if ($payload['operation'] != 'Sale') {
      // Authorization code is only needed to Auth and Capture operations.
      $remote_id .= '|' . $response['authorizationCode'];
    }
    $payment->setRemoteId($remote_id);
    $payment->setAuthorizedTime($this->time->getRequestTime());
    $payment->setAvsResponseCode($response['responseCode']);
    $response_label = $response['responseDescription'] . ' (' . $response['correlationID'] . ')';
    $payment->setAvsResponseCodeLabel($response_label);
    $payment->save();
  }

  /**
   * {@inheritdoc}
   */
  public function capturePayment(PaymentInterface $payment, ?Price $amount = NULL) {
    $this->assertPaymentState($payment, ['authorization']);
    $payment_method = $payment->getPaymentMethod();
    $this->assertPaymentMethod($payment_method);

    // If not specified, capture the entire amount.
    $amount = $amount ?: $payment->getAmount();

    $valitor_api = $this->initializeValitorApi();
    $remote_id = explode('|', $payment->getRemoteId());
    $payload = [
      'operation' => "Capture",
      'virtualCardNumber' => $payment_method->getRemoteId(),
      'virtualCardPaymentAdditionalData' => [
        'merchantReferenceData' => $payment->getOrderId(),
      ],
      'acquirerReferenceNumber' => !empty($remote_id[1]) ? $remote_id[1] : '',
      'authorizationCode' => !empty($remote_id[2]) ? $remote_id[2] : '',
      'currency' => $amount->getCurrencyCode(),
      'amount' => $this->getMinorUnits($amount),
      'isFinalCapture' => TRUE,
      'systemCalling' => $this->getSystemCalling(),
    ];
    $response = $valitor_api->createVirtualCardPayment($payload);
    if ($response) {
      $response['order_id'] = $payment->getOrderId();
    }
    // Process response errors.
    $this->handleDecline($response);

    $payment->setState('completed');
    $payment->setRemoteId($response['transactionID'] . '|' . $response['acquirerReferenceNumber'] . '|' . $response['authorizationCode']);
    $payment->setAmount($amount);
    $payment->setAvsResponseCode($response['responseCode']);
    $response_label = $response['responseDescription'] . ' (' . $response['correlationID'] . ')';
    $payment->setAvsResponseCodeLabel($response_label);
    $payment->setCompletedTime($this->time->getRequestTime());
    $payment->save();
  }

  /**
   * {@inheritdoc}
   */
  public function voidPayment(PaymentInterface $payment) {
    $this->assertPaymentState($payment, ['authorization']);
    $payment_method = $payment->getPaymentMethod();
    $this->assertPaymentMethod($payment_method);

    $valitor_api = $this->initializeValitorApi();
    $amount = $payment->getAmount();
    $remote_id = explode('|', $payment->getRemoteId());
    $payload = [
      'operation' => "Capture",
      'virtualCardNumber' => $payment_method->getRemoteId(),
      'virtualCardPaymentAdditionalData' => [
        'merchantReferenceData' => $payment->getOrderId(),
      ],
      'acquirerReferenceNumber' => !empty($remote_id[1]) ? $remote_id[1] : '',
      'authorizationCode' => !empty($remote_id[2]) ? $remote_id[2] : '',
      'currency' => $amount->getCurrencyCode(),
      'amount' => 0,
      'isFinalCapture' => TRUE,
      'systemCalling' => $this->getSystemCalling(),
    ];
    $response = $valitor_api->createVirtualCardPayment($payload);
    if ($response) {
      $response['order_id'] = $payment->getOrderId();
    }
    // Process response errors.
    $this->handleDecline($response);

    $payment->setState('authorization_voided');
    $payment->setRemoteId($response['transactionID'] . '|' . $response['acquirerReferenceNumber'] . '|' . $response['authorizationCode']);
    $payment->setAuthorizedTime($this->time->getRequestTime());
    $payment->setAvsResponseCode($response['responseCode']);
    $response_label = $response['responseDescription'] . ' (' . $response['correlationID'] . ')';
    $payment->setAvsResponseCodeLabel($response_label);
    $payment->save();
  }

  /**
   * {@inheritdoc}
   */
  public function refundPayment(PaymentInterface $payment, ?Price $amount = NULL) {
    $this->assertPaymentState($payment, ['completed', 'partially_refunded']);
    $payment_method = $payment->getPaymentMethod();
    $this->assertPaymentMethod($payment_method);

    // If not specified, refund the entire amount.
    $amount = $amount ?: $payment->getAmount();
    $this->assertRefundAmount($payment, $amount);

    $valitor_api = $this->initializeValitorApi();

    $payload = [
      'virtualCardNumber' => $payment_method->getRemoteId(),
      'virtualCardPaymentAdditionalData' => [
        'merchantReferenceData' => $payment->getOrderId(),
      ],
      'currency' => $amount->getCurrencyCode(),
      'amount' => $this->getMinorUnits($amount),
      'systemCalling' => $this->getSystemCalling(),
    ];

    $remote_id = explode('|', $payment->getRemoteId());
    if (!empty($remote_id[1])) {
      $payload += [
        'acquirerReferenceNumber' => $remote_id[1],
      ];
    }
    if (!empty($remote_id[2])) {
      // There was authorization and capture.
      $payload += [
        'operation' => 'CaptureRefund',
        'authorizationCode' => $remote_id[2],
      ];
    }
    else {
      // Previous operation was "Sale".
      $payload += [
        'operation' => 'Refund',
      ];
    }
    $response = $valitor_api->createVirtualCardPayment($payload);
    if ($response) {
      $response['order_id'] = $payment->getOrderId();
    }
    // Process response errors.
    $this->handleDecline($response);

    $old_refunded_amount = $payment->getRefundedAmount();
    $new_refunded_amount = $old_refunded_amount->add($amount);
    if ($new_refunded_amount->lessThan($payment->getAmount())) {
      $payment->setState('partially_refunded');
    }
    else {
      $payment->setState('refunded');
    }

    $payment->setRefundedAmount($new_refunded_amount);
    $remote_id = $response['transactionID'] . '|' . $response['acquirerReferenceNumber'];
    if (!empty($response['authorizationCode'])) {
      $remote_id .= '|' . $response['authorizationCode'];
    }
    $payment->setRemoteId($remote_id);
    $payment->setAuthorizedTime($this->time->getRequestTime());
    $payment->setAvsResponseCode($response['responseCode']);
    $response_label = $response['responseDescription'] . ' (' . $response['correlationID'] . ')';
    $payment->setAvsResponseCodeLabel($response_label);
    $payment->save();
  }

  /**
   * {@inheritdoc}
   */
  public function createPaymentMethod(PaymentMethodInterface $payment_method, array $payment_details) {
    foreach (['type', 'number', 'expiration'] as $required_key) {
      if (empty($payment_details[$required_key])) {
        throw new \InvalidArgumentException(sprintf('$payment_details must contain the %s key.', $required_key));
      }
    }

    $payment_method->card_type = $payment_details['type'];
    $payment_method->card_number = substr($payment_details['number'], -4);
    $payment_method->card_exp_month = $payment_details['expiration']['month'];
    $payment_method->card_exp_year = $payment_details['expiration']['year'];
    $expires = CreditCard::calculateExpirationTimestamp($payment_details['expiration']['month'], $payment_details['expiration']['year']);

    // Get the virtual credit card number.
    $valitor_api = $this->initializeValitorApi();
    $payload = [
      'cardNumber' => $payment_details['number'],
      'expirationMonth' => $payment_details['expiration']['month'],
      'expirationYear' => $payment_details['expiration']['year'],
      'cvc' => $payment_details['security_code'],
      'subsequentTransactionType' => "MerchantInitiatedCredentialOnFile",
      'cardVerificationData' => [
        'cavv' => $payment_details['cavv'],
        'xid' => $payment_details['xid'],
        'dsTransId' => $payment_details['dsTransId'],
        'mdStatus' => static::getMdStatus($payment_details['mdStatus']),
      ],
      'systemCalling' => $this->getSystemCalling(),
    ];
    $response = $valitor_api->createVirtualCard($payload);

    // Process response errors.
    $this->handleDecline($response);
    $payment_method->setRemoteId($response['virtualCard']);
    $payment_method->setExpiresTime($expires);
    $payment_method->save();
  }

  /**
   * Get enum value of the mdStatus for request.
   *
   * @param int $status_id
   *   Numeric value for mdStatus.
   *
   * @return string
   *   Enum value of mdStatus or empty string
   *   if the status id is invalid.
   */
  public static function getMdStatus($status_id) {
    $status_list = [
      1 => "MdFullyAuthenticated",
      2 => "MdNotEnrolled",
      4 => "MdAttempt",
      5 => "MdUReceived",
    ];
    if (!empty($status_list[$status_id])) {
      return $status_list[$status_id];
    }
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function deletePaymentMethod(PaymentMethodInterface $payment_method) {
    $payment_method->delete();
  }

  /**
   * {@inheritdoc}
   */
  public function updatePaymentMethod(PaymentMethodInterface $payment_method) {
    /** @var \Drupal\commerce_payment\Plugin\Commerce\PaymentMethodType\CreditCard $payment_method */
    $valitor_api = $this->initializeValitorApi();
    $payload = [
      'virtualCardNumber' => $payment_method->getRemoteId(),
      'expirationMonth' => $payment_method->card_exp_month->value,
      'expirationYear' => $payment_method->card_exp_year->value,
      'systemCalling' => $this->getSystemCalling(),
    ];
    $response = $valitor_api->updateExpirationDate($payload);

    // Process response errors if any.
    $this->handleDecline($response);

    $payment_method->save();
  }

  /**
   * Initialize a new ValitorApi object.
   *
   * @return \Drupal\commerce_valitor\ValitorPayApiInterface
   *   The Valitor pay API interface.
   *
   * @throws \Drupal\commerce_payment\Exception\PaymentGatewayException
   */
  protected function initializeValitorApi() {
    static $valitor_api = NULL;

    if (!$valitor_api) {
      $valitor_api = new ValitorPayApi(
        $this->client,
        $this->configuration['api_key'],
        $this->configuration['contract_number'],
        $this->configuration['pos_id'],
        $this->configuration['mode'],
        $this->logger
      );
    }

    return $valitor_api;
  }

}
