<?php

namespace Drupal\fkr_giftcard\Controller;

use Drupal\commerce_cart\CartSession;
use Drupal\commerce_cart\CartSessionInterface;
use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Handles gift card checkout via Commerce + Valitor.
 */
class GiftCardController extends ControllerBase {

  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    CartSessionInterface $cart_session,
    AccountProxyInterface $current_user,
    RequestStack $request_stack,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->cartSession = $cart_session;
    $this->currentUser = $current_user;
    $this->requestStack = $request_stack;
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('commerce_cart.cart_session'),
      $container->get('current_user'),
      $container->get('request_stack'),
    );
  }

  /**
   * GET /api/fkr/giftcard/amounts
   * Returns available gift card amounts from Commerce products.
   */
  public function amounts(): JsonResponse {
    $variations = $this->entityTypeManager->getStorage('commerce_product_variation')
      ->loadByProperties(['type' => 'default', 'status' => 1]);

    $amounts = [];
    foreach ($variations as $variation) {
      $sku = $variation->getSku();
      if (strpos($sku, 'GJAFABREF-') === 0) {
        $amount    = (int) str_replace('GJAFABREF-', '', $sku);
        $amounts[] = [
          'sku'    => $sku,
          'amount' => $amount,
          'label'  => number_format($amount, 0, '.', '.') . ' kr',
        ];
      }
    }

    usort($amounts, fn($a, $b) => $a['amount'] - $b['amount']);

    return new JsonResponse($amounts, 200, $this->cors());
  }

  /**
   * POST /api/fkr/giftcard/checkout
   * Creates a Commerce order and returns the checkout URL.
   *
   * Expected body: { sku, buyer_name, recipient_name, email, phone, notes }
   */
  public function checkout(Request $request): JsonResponse {
    if ($request->getMethod() === 'OPTIONS') {
      return new JsonResponse([], 200, $this->cors());
    }

    $data = json_decode($request->getContent(), TRUE);

    foreach (['sku', 'buyer_name', 'recipient_name', 'email', 'phone'] as $field) {
      if (empty($data[$field])) {
        return new JsonResponse(['error' => "Missing required field: $field"], 400, $this->cors());
      }
    }

    $variations = $this->entityTypeManager->getStorage('commerce_product_variation')
      ->loadByProperties(['sku' => $data['sku'], 'status' => 1]);

    if (empty($variations)) {
      return new JsonResponse(['error' => 'Invalid gift card amount.'], 400, $this->cors());
    }

    $variation = reset($variations);

    $stores = $this->entityTypeManager->getStorage('commerce_store')->loadMultiple();
    $store  = reset($stores);

    $order_item = OrderItem::create([
      'type'             => 'default',
      'purchased_entity' => $variation,
      'quantity'         => 1,
      'unit_price'       => $variation->getPrice(),
    ]);
    $order_item->save();

    $gift_info = sprintf(
      'Kaupandi: %s | Viðtakandi: %s | Sími: %s | Athugasemd: %s',
      $data['buyer_name'],
      $data['recipient_name'],
      $data['phone'],
      $data['notes'] ?? ''
    );

    $order = Order::create([
      'type'        => 'default',
      'state'       => 'draft',
      'mail'        => $data['email'],
      'uid'         => 0,
      'store_id'    => $store->id(),
      'cart'        => TRUE,
      'order_items' => [$order_item],
      'notes'       => $gift_info,
    ]);
    $order->save();

    $base_url    = $this->requestStack->getCurrentRequest()->getSchemeAndHttpHost();
    $checkout_url = $base_url . '/checkout/giftcard/start/' . $order->id();

    return new JsonResponse(['checkout_url' => $checkout_url], 200, $this->cors());
  }

  /**
   * GET /checkout/giftcard/start/{order_id}
   * Adds the order to the browser cart session then redirects to Commerce checkout.
   */
  public function startCheckout(int $order_id): RedirectResponse {
    $order = $this->entityTypeManager->getStorage('commerce_order')->load($order_id);

    if ($order && $order->getState()->getId() !== 'canceled') {
      if ($this->currentUser->isAuthenticated() && $order->getCustomerId() == 0) {
        $order->setCustomerId($this->currentUser->id());
        $order->save();
      }
      else {
        $this->cartSession->addCartId($order_id, CartSession::ACTIVE);
      }
      Cache::invalidateTags($order->getCacheTagsToInvalidate());
    }

    return new RedirectResponse('/checkout/' . $order_id);
  }

  private function cors(): array {
    return [
      'Access-Control-Allow-Origin'  => '*',
      'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
      'Access-Control-Allow-Headers' => 'Content-Type',
    ];
  }

}
