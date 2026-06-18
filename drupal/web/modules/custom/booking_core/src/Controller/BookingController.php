<?php

namespace Drupal\booking_core\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controller for booking pages.
 */
class BookingController extends ControllerBase {

  /**
   * Returns the booking thank-you page render array.
   */
  public function thankYou(): array {
    return [
      '#markup' => $this->t('Your booking has been received. We will be in touch shortly.'),
    ];
  }

}
