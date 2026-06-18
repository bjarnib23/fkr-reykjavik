<?php

namespace Drupal\giftcard_core;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * Defines the list builder for GiftCard entities.
 */
class GiftCardListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['code']           = $this->t('Code');
    $header['recipient_name'] = $this->t('Recipient');
    $header['sender_name']    = $this->t('Sender');
    $header['amount']         = $this->t('Amount');
    $header['status']         = $this->t('Status');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\giftcard_core\GiftCardInterface $entity */
    $row['code']           = $entity->toLink($entity->getCode());
    $row['recipient_name'] = $entity->getRecipientName();
    $row['sender_name']    = $entity->getSenderName();
    $row['amount']         = $entity->getAmount() . ' ' . $entity->getCurrency();
    $allowed = $entity->get('status')->getFieldDefinition()->getSetting('allowed_values');
    $row['status'] = $allowed[$entity->getStatus()] ?? $entity->getStatus();
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityIds(): array {
    return $this->getStorage()->getQuery()
      ->sort('created', 'DESC')
      ->accessCheck(FALSE)
      ->execute();
  }

}
