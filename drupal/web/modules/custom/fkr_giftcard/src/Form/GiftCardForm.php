<?php

namespace Drupal\fkr_giftcard\Form;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Url;
use Drupal\fkr_rapyd\RapydClient;
use Symfony\Component\DependencyInjection\ContainerInterface;

class GiftCardForm extends FormBase {

  protected EntityTypeManagerInterface $entityTypeManager;
  protected RapydClient $rapydClient;

  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    RapydClient $rapyd_client,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->rapydClient       = $rapyd_client;
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('fkr_rapyd.client'),
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

    $form['submit'] = [
      '#type'  => 'submit',
      '#value' => $this->t('Proceed to payment'),
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $sku   = $form_state->getValue('sku');
    $name  = $form_state->getValue('name');
    $email = $form_state->getValue('email');
    $phone = $form_state->getValue('phone');
    $notes = $form_state->getValue('notes') ?? '';

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

    $order_item = OrderItem::create([
      'type'             => 'default',
      'purchased_entity' => $variation,
      'quantity'         => 1,
      'unit_price'       => $variation->getPrice(),
    ]);
    $order_item->save();

    $order = Order::create([
      'type'              => 'default',
      'state'             => 'draft',
      'mail'              => $email,
      'uid'               => 0,
      'store_id'          => $store->id(),
      'order_items'       => [$order_item],
      'customer_comments' => sprintf('Kaupandi: %s | Sími: %s | Athugasemd: %s', $name, $phone, $notes),
    ]);
    $order->save();

    try {
      $amount       = (int) $variation->getPrice()->getNumber();
      $complete_url = Url::fromRoute('fkr_giftcard.thankyou', ['order_id' => $order->id()], ['absolute' => TRUE])->toString();
      $error_url    = Url::fromRoute('fkr_giftcard.cancel', [], ['absolute' => TRUE])->toString();
      $result       = $this->rapydClient->createCheckout($order->id(), $amount, $email, $complete_url, $error_url);
      $form_state->setResponse(new TrustedRedirectResponse($result['redirect_url']));
    }
    catch (\RuntimeException $e) {
      $order->delete();
      $this->messenger()->addError($this->t('Payment service unavailable. Please try again.'));
    }
  }

}
