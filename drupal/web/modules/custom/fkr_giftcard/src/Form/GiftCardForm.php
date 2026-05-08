<?php

namespace Drupal\fkr_giftcard\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\fkr_rapyd\RapydClient;
use Drupal\user\UserDataInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class GiftCardForm extends FormBase {

  protected EntityTypeManagerInterface $entityTypeManager;
  protected RapydClient $rapydClient;
  protected UserDataInterface $userData;
  protected AccountProxyInterface $currentUser;

  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    RapydClient $rapyd_client,
    UserDataInterface $user_data,
    AccountProxyInterface $current_user,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->rapydClient       = $rapyd_client;
    $this->userData          = $user_data;
    $this->currentUser       = $current_user;
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('fkr_rapyd.client'),
      $container->get('user.data'),
      $container->get('current_user'),
    );
  }

  public function getFormId(): string {
    return 'fkr_giftcard_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $variations = $this->entityTypeManager->getStorage('commerce_product_variation')
      ->loadByProperties(['type' => 'default', 'status' => 1]);

    $raw = [];
    foreach ($variations as $variation) {
      $sku = $variation->getSku();
      if (str_starts_with($sku, 'GJAFABREF-')) {
        $raw[$sku] = (int) str_replace('GJAFABREF-', '', $sku);
      }
    }
    asort($raw);

    $amounts = ['' => $this->t('— Select an amount —')];
    foreach ($raw as $sku => $amount) {
      $amounts[$sku] = number_format($amount, 0, '.', '.') . ' kr';
    }

    $uid        = $this->currentUser->id();
    $is_logged  = $uid > 0;
    $saved_card = $is_logged ? $this->userData->get('fkr_giftcard', $uid, 'saved_card') : NULL;

    if ($is_logged && $saved_card) {
      $form['payment_method'] = [
        '#type'          => 'radios',
        '#title'         => $this->t('Payment method'),
        '#options'       => [
          'saved' => $this->t('**** **** **** @last4 (@brand)', [
            '@last4' => $saved_card['last_four'],
            '@brand' => $saved_card['brand'],
          ]),
          'new' => $this->t('Use a different card'),
        ],
        '#default_value' => 'saved',
      ];
    }

    $form['sku'] = [
      '#type'     => 'select',
      '#title'    => $this->t('Amount'),
      '#options'  => $amounts,
      '#required' => TRUE,
    ];

    $form['name'] = [
      '#type'     => 'textfield',
      '#title'    => $this->t('Name'),
      '#required' => TRUE,
    ];

    $form['email'] = [
      '#type'     => 'email',
      '#title'    => $this->t('Email'),
      '#required' => TRUE,
    ];

    $form['phone'] = [
      '#type'     => 'tel',
      '#title'    => $this->t('Phone'),
      '#required' => TRUE,
    ];

    $form['notes'] = [
      '#type'  => 'textarea',
      '#title' => $this->t('Notes'),
    ];

    if ($is_logged && !$saved_card) {
      $form['save_card'] = [
        '#type'  => 'checkbox',
        '#title' => $this->t('Save card for future use'),
      ];
    }

    $form['submit'] = [
      '#type'  => 'submit',
      '#value' => $this->t('Proceed to payment'),
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $sku           = $form_state->getValue('sku');
    $name          = $form_state->getValue('name');
    $email         = $form_state->getValue('email');
    $phone         = $form_state->getValue('phone');
    $notes         = $form_state->getValue('notes') ?? '';
    $save_card     = (bool) $form_state->getValue('save_card');
    $payment_choice = $form_state->getValue('payment_method');
    $uid           = $this->currentUser->id();
    $saved_card    = $uid > 0 ? $this->userData->get('fkr_giftcard', $uid, 'saved_card') : NULL;

    $variations = $this->entityTypeManager->getStorage('commerce_product_variation')
      ->loadByProperties(['sku' => $sku, 'status' => 1]);

    if (empty($variations)) {
      $this->messenger()->addError($this->t('Invalid gift card amount.'));
      return;
    }

    $variation = reset($variations);
    $stores    = $this->entityTypeManager->getStorage('commerce_store')->loadMultiple();
    $store     = reset($stores);

    if (!$store) {
      $this->messenger()->addError($this->t('No store configured.'));
      return;
    }

    $order_item = $this->entityTypeManager->getStorage('commerce_order_item')->create([
      'type'             => 'default',
      'purchased_entity' => $variation,
      'quantity'         => 1,
      'unit_price'       => $variation->getPrice(),
    ]);
    $order_item->save();

    $order = $this->entityTypeManager->getStorage('commerce_order')->create([
      'type'              => 'default',
      'state'             => 'draft',
      'mail'              => $email,
      'uid'               => $uid ?: 0,
      'store_id'          => $store->id(),
      'order_items'       => [$order_item],
      'customer_comments' => implode(' | ', [
        'Kaupandi: ' . $name,
        'Sími: ' . $phone,
        'Athugasemd: ' . $notes,
      ]),
    ]);
    $order->save();

    $amount = (int) $variation->getPrice()->getNumber();

    try {
      $customer_id       = $saved_card['customer_id'] ?? NULL;
      $payment_method_id = $saved_card['payment_method_id'] ?? NULL;

      if ($payment_choice === 'saved' && $customer_id && $payment_method_id) {

        $result = $this->rapydClient->createDirectPayment($order->id(), $amount, $customer_id, $payment_method_id, $email);

        $status = $result['status'] ?? '';
        if (in_array($status, ['CLO', 'ACT', 'PEN'])) {
          $this->messenger()->addStatus($this->t('Payment successful. You will receive your gift card by email shortly.'));
        }
        else {
          $order->delete();
          $this->messenger()->addError($this->t('Payment failed. Please try again.'));
        }
        return;
      }

      $complete_url = Url::fromRoute('fkr_giftcard.thankyou', [], ['absolute' => TRUE])->toString();
      $error_url    = Url::fromRoute('fkr_giftcard.cancel', [], ['absolute' => TRUE])->toString();

      if ($save_card && $uid > 0) {
        $existing_customer_id = $this->userData->get('fkr_giftcard', $uid, 'rapyd_customer_id');
        if (!$existing_customer_id) {
          $existing_customer_id = $this->rapydClient->createCustomer($email, $name);
          $this->userData->set('fkr_giftcard', $uid, 'rapyd_customer_id', $existing_customer_id);
        }
        $customer_id = $existing_customer_id;

        $complete_url = Url::fromRoute('fkr_giftcard.thankyou', [], [
          'query'    => ['save_card' => 1, 'uid' => $uid],
          'absolute' => TRUE,
        ])->toString();
      }

      $result = $this->rapydClient->createCheckout($order->id(), $amount, $email, $complete_url, $error_url, $customer_id);
      $form_state->setResponse(new TrustedRedirectResponse($result['redirect_url']));
    }
    catch (\RuntimeException $e) {
      $order->delete();
      $this->messenger()->addError($this->t('Payment service unavailable. Please try again.'));
    }
  }

}
