<?php

namespace Drupal\booking_core\Form;

use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

/**
 * Confirmation form for deleting a Booking entity.
 */
class BookingDeleteForm extends ContentEntityDeleteForm {

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): TranslatableMarkup {
    return $this->t('Are you sure you want to delete the booking for %name?', [
      '%name' => $this->entity->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    return new Url('entity.booking.collection');
  }

  /**
   * {@inheritdoc}
   */
  protected function getDeletionMessage(): TranslatableMarkup {
    return $this->t('Booking for %name has been deleted.', [
      '%name' => $this->entity->label(),
    ]);
  }

}
