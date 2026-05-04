<?php

namespace Drupal\fkr_giftcard\Controller;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\fkr_rapyd\RapydClient;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class GiftCardController extends ControllerBase {

  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    private RapydClient $rapydClient,
  ) {
    $this->entityTypeManager = $entity_type_manager;
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('fkr_rapyd.client'),
    );
  }

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

  public function checkout(Request $request): JsonResponse {
    if ($request->getMethod() === 'OPTIONS') {
      return new JsonResponse([], 200, $this->cors());
    }

    $data = json_decode($request->getContent(), TRUE);
    if (!is_array($data)) {
      return new JsonResponse(['error' => 'Invalid JSON body.'], 400, $this->cors());
    }

    foreach (['sku', 'name', 'email', 'phone'] as $field) {
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
    $stores    = $this->entityTypeManager->getStorage('commerce_store')->loadMultiple();
    $store     = reset($stores);
    if (!$store) {
      return new JsonResponse(['error' => 'No Commerce store configured.'], 500, $this->cors());
    }

    $order_item = OrderItem::create([
      'type'             => 'default',
      'purchased_entity' => $variation,
      'quantity'         => 1,
      'unit_price'       => $variation->getPrice(),
    ]);
    $order_item->save();

    $notes = sprintf(
      'Kaupandi: %s | Sími: %s | Athugasemd: %s',
      $data['name'],
      $data['phone'],
      $data['notes'] ?? ''
    );

    $order = Order::create([
      'type'        => 'default',
      'state'       => 'draft',
      'mail'        => $data['email'],
      'uid'         => 0,
      'store_id'    => $store->id(),
      'order_items'       => [$order_item],
      'customer_comments' => $notes,
    ]);
    $order->save();

    try {
      $amount = (int) $variation->getPrice()->getNumber();
      $result = $this->rapydClient->createCheckout($order->id(), $amount, $data['email']);
      return new JsonResponse(['checkout_url' => $result['redirect_url']], 200, $this->cors());
    }
    catch (\RuntimeException $e) {
      $order->delete();
      return new JsonResponse(['error' => 'Payment service unavailable. Please try again.'], 503, $this->cors());
    }
  }

  private function cors(): array {
    return [
      'Access-Control-Allow-Origin'  => '*',
      'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
      'Access-Control-Allow-Headers' => 'Content-Type',
    ];
  }

}
