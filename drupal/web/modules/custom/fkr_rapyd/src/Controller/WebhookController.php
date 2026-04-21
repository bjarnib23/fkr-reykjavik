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
    private EntityTypeManagerInterface $entityTypeManager,
    private MailManagerInterface $mailManager,
    private LoggerChannelFactoryInterface $loggerFactory,
  ) {}

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

    if (!$this->rapydClient->verifyWebhook($raw_body, $salt, $timestamp, $signature, $request->getPathInfo())) {
      $this->loggerFactory->get('fkr_rapyd')->warning('Webhook signature verification failed.');
      return new JsonResponse(['error' => 'Invalid signature'], 401);
    }

    $payload = json_decode($raw_body, TRUE);
    $type    = $payload['type'] ?? '';

    if ($type !== 'PAYMENT_COMPLETED') {
      return new JsonResponse(['status' => 'ignored']);
    }

    $order_id = $payload['data']['metadata']['order_id'] ?? NULL;
    if (!$order_id) {
      $this->loggerFactory->get('fkr_rapyd')->error('Webhook missing order_id in metadata.');
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
    // Commerce default workflow: draft → place → pending → fulfill → completed
    $order->getState()->applyTransitionById('place');
    $order->getState()->applyTransitionById('fulfill');
    $order->save();

    $email    = $order->getEmail();
    $name     = $order->getEmail();
    $amount   = (int) $order->getTotalPrice()->getNumber();
    $langcode = \Drupal::config('system.site')->get('langcode');

    try {
      $this->mailManager->mail('fkr_rapyd', 'giftcard_confirmation', $email, $langcode, [
        'name'   => $name,
        'code'   => $code,
        'amount' => $amount,
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

}
