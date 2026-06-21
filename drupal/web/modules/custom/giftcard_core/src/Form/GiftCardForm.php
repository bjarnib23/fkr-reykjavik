<?php

namespace Drupal\giftcard_core\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Admin add/edit form for GiftCard entities.
 */
class GiftCardForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $entity = $this->entity;
    $status = parent::save($form, $form_state);

    if ($status === SAVED_NEW) {
      $this->messenger()->addStatus($this->t('Gift card %code has been created.', [
        '%code' => $entity->label(),
      ]));
    }
    else {
      $this->messenger()->addStatus($this->t('Gift card %code has been updated.', [
        '%code' => $entity->label(),
      ]));
    }

    $form_state->setRedirect('entity.gift_card.canonical', ['gift_card' => $entity->id()]);
    return $status;
  }

}
