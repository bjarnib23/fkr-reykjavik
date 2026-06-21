<?php

namespace Drupal\giftcard_core\Form;

use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

/**
 * Confirmation form for deleting a GiftCard entity.
 */
class GiftCardDeleteForm extends ContentEntityDeleteForm {

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): TranslatableMarkup {
    return $this->t('Are you sure you want to delete gift card %code?', [
      '%code' => $this->entity->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    return new Url('entity.gift_card.collection');
  }

  /**
   * {@inheritdoc}
   */
  protected function getDeletionMessage(): TranslatableMarkup {
    return $this->t('Gift card %code has been deleted.', [
      '%code' => $this->entity->label(),
    ]);
  }

}
