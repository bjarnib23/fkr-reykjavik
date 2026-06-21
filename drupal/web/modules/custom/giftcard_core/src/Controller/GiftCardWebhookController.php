<?php

namespace Drupal\giftcard_core\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\giftcard_core\GiftCardService;
use Drupal\giftcard_core\PaymentClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Receives and processes payment webhooks.
 */
class GiftCardWebhookController extends ControllerBase {

  /**
   * Constructs a new GiftCardWebhookController.
   *
   * @param \Drupal\giftcard_core\GiftCardService $giftCardService
   *   The gift card service.
   * @param \Drupal\giftcard_core\PaymentClientInterface $paymentClient
   *   The payment client.
   * @param \Drupal\Core\Mail\MailManagerInterface $mailManager
   *   The mail manager.
   */
  public function __construct(
    private readonly GiftCardService $giftCardService,
    private readonly PaymentClientInterface $paymentClient,
    private readonly MailManagerInterface $mailManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new self(
      $container->get('giftcard_core.gift_card_service'),
      $container->get('giftcard_core.payment_client'),
      $container->get('plugin.manager.mail'),
    );
  }

  /**
   * Handles an inbound payment webhook request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The incoming HTTP request.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   An HTTP response.
   */
  public function receive(Request $request): Response {
    if (!$this->paymentClient->verifyWebhook($request)) {
      $this->getLogger('giftcard_core')->warning('Received webhook with invalid or replayed signature.');
      return new Response('Forbidden', Response::HTTP_FORBIDDEN);
    }

    $paymentId = $this->paymentClient->extractCompletedPaymentId($request);
    if ($paymentId === NULL) {
      return new Response('OK', Response::HTTP_OK);
    }

    $checkoutData = $this->giftCardService->getCheckoutDataByPaymentId($paymentId);
    if ($checkoutData === NULL) {
      $this->getLogger('giftcard_core')->warning(
        'No checkout data found for payment @id.',
        ['@id' => $paymentId]
      );
      return new Response('OK', Response::HTTP_OK);
    }

    $giftCard = $this->giftCardService->createGiftCard($checkoutData);

    if ($giftCard === NULL) {
      return new Response('Internal Server Error', Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    $this->giftCardService->deleteCheckoutDataByPaymentId($paymentId);

    $langcode = $this->config('system.site')->get('langcode');

    $this->mailManager->mail(
      'giftcard_core',
      'gift_card_sender',
      $giftCard->getSenderEmail(),
      $langcode,
      ['gift_card' => $giftCard]
    );

    $this->mailManager->mail(
      'giftcard_core',
      'gift_card_recipient',
      $giftCard->getRecipientEmail(),
      $langcode,
      ['gift_card' => $giftCard]
    );

    return new Response('OK', Response::HTTP_OK);
  }

}
