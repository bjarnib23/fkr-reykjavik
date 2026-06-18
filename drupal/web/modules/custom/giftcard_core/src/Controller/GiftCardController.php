<?php

namespace Drupal\giftcard_core\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\giftcard_core\GiftCardService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for gift card purchase pages.
 */
class GiftCardController extends ControllerBase {

  /**
   * Constructs a new GiftCardController.
   *
   * @param \Drupal\giftcard_core\GiftCardService $giftCardService
   *   The gift card service.
   */
  public function __construct(
    private readonly GiftCardService $giftCardService,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new self(
      $container->get('giftcard_core.gift_card_service'),
    );
  }

  /**
   * Renders the purchase confirmation page.
   *
   * @return array
   *   A render array.
   */
  public function thankYou(): array {
    $data = $this->giftCardService->getCheckoutData();
    $this->giftCardService->clearCheckoutData();

    if ($data) {
      return [
        '#markup' => $this->t(
          'Thank you, @sender! Your gift card for @recipient has been sent to @email.',
          [
            '@sender'    => $data['sender_name'],
            '@recipient' => $data['recipient_name'],
            '@email'     => $data['recipient_email'],
          ]
        ),
        '#cache'  => ['contexts' => ['session']],
      ];
    }

    return [
      '#markup' => $this->t('Thank you! Your gift card purchase is complete.'),
      '#cache'  => ['contexts' => ['session']],
    ];
  }

  /**
   * Renders the purchase cancellation page.
   *
   * @return array
   *   A render array.
   */
  public function cancel(): array {
    $this->giftCardService->clearCheckoutData();

    return [
      '#markup' => $this->t('Your purchase was cancelled. No payment has been taken.'),
      '#cache'  => ['contexts' => ['session']],
    ];
  }

}
