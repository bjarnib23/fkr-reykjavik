<?php

namespace Drupal\fkr_rapyd\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\fkr_rapyd\RapydClient;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Handles incoming Rapyd webhook notifications.
 */
class WebhookController extends ControllerBase {

  public function __construct(
    private RapydClient $rapydClient,
    EntityTypeManagerInterface $entityTypeManager,
    private MailManagerInterface $mailManager,
    LoggerChannelFactoryInterface $loggerFactory,
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->loggerFactory = $loggerFactory;
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('fkr_rapyd.client'),
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.mail'),
      $container->get('logger.factory'),
    );
  }

  public function handle(Request $request): JsonResponse {
    $raw_body  = $request->getContent();
    $salt      = $request->headers->get('rapyd-idempotency', '');
    $timestamp = $request->headers->get('rapyd-timestamp', '');
    $signature = $request->headers->get('rapyd-signature', '');
    $path      = $request->getPathInfo();

    $this->loggerFactory->get('fkr_rapyd')->debug('Webhook headers: salt=@s ts=@t sig=@sig path=@path', [
      '@s'    => $salt,
      '@t'    => $timestamp,
      '@sig'  => $signature,
      '@path' => $path,
    ]);

    if (!$this->rapydClient->verifyWebhook($raw_body, $salt, $timestamp, $signature, $path)) {
      $this->loggerFactory->get('fkr_rapyd')->warning('Webhook signature verification failed.');
      return new JsonResponse(['error' => 'Invalid signature'], 401);
    }

    $payload = json_decode($raw_body, TRUE);
    $type    = $payload['type'] ?? '';

    if ($type !== 'PAYMENT_COMPLETED') {
      return new JsonResponse(['status' => 'ignored']);
    }

    $ref = $payload['data']['merchant_reference_id'] ?? '';
    preg_match('/^fkr-order-(\d+)$/', $ref, $m);
    $order_id = $m[1] ?? NULL;
    if (!$order_id) {
      $this->loggerFactory->get('fkr_rapyd')->error('Webhook missing order_id in merchant_reference_id: @ref', ['@ref' => $ref]);
      return new JsonResponse(['status' => 'ok']);
    }

    $storage = $this->entityTypeManager->getStorage('commerce_order');
    $order   = $storage->load($order_id);

    if (!$order) {
      $this->loggerFactory->get('fkr_rapyd')->error('Webhook: order @id not found.', ['@id' => $order_id]);
      return new JsonResponse(['status' => 'ok']);
    }

    if ($order->getState()->getId() === 'completed') {
      return new JsonResponse(['status' => 'ok']);
    }

    $code = strtoupper(substr(bin2hex(random_bytes(6)), 0, 10));
    $order->set('field_giftcard_code', $code);
    $order->getState()->applyTransitionById('place');
    $order->save();

    $email    = $order->getEmail();
    $notes    = $order->hasField('customer_comments') ? ($order->get('customer_comments')->value ?? '') : '';
    preg_match('/Kaupandi:\s*([^|]+)/', $notes, $m);
    $name     = trim($m[1] ?? $email);
    $amount   = (int) $order->getTotalPrice()->getNumber();
    $langcode = $this->config('system.site')->get('langcode');

    $cardLabels = $this->loadGiftCardLabels();

    try {
      $this->mailManager->mail('fkr_rapyd', 'giftcard_confirmation', $email, $langcode, [
        'name'         => $name,
        'code'         => $code,
        'amount'       => $amount,
        'card_labels'  => $cardLabels,
      ]);
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('fkr_rapyd')->error('Gift card email failed for order @id: @msg', [
        '@id'  => $order_id,
        '@msg' => $e->getMessage(),
      ]);
    }

    return new JsonResponse(['status' => 'ok']);
  }

  private function loadGiftCardLabels(): array {
    $storage = $this->entityTypeManager->getStorage('node');

    $gcNodes = $storage->loadByProperties(['type' => 'giftcard_page', 'status' => 1]);
    $gcNode  = reset($gcNodes);

    $settingsNodes = $storage->loadByProperties(['type' => 'site_settings', 'status' => 1]);
    $settingsNode  = reset($settingsNodes);

    $val = fn($node, $field) => ($node && $node->hasField($field) && !$node->get($field)->isEmpty())
      ? $node->get($field)->value
      : '';

    return [
      'brand'           => $this->config('system.site')->get('name'),
      'card_title'      => $val($gcNode, 'field_card_title'),
      'card_code_label' => $val($gcNode, 'field_card_code_label'),
      'card_no_expiry'  => $val($gcNode, 'field_card_no_expiry'),
      'email_greeting'  => $val($gcNode, 'field_email_greeting'),
      'email_signoff'   => $val($gcNode, 'field_email_signoff'),
      'frontend_url'    => rtrim($this->config('fkr_rapyd.settings')->get('frontend_url') ?? '', '/'),
    ];
  }

}
