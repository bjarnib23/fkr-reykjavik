<?php

namespace Drupal\booking_core;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityViewBuilder;

/**
 * View builder for Booking entities.
 *
 * Delegates field rendering to the entity display system so that site builders
 * can reorder fields via Manage Display, view modes work, field-level access is
 * respected, and the output is themeable through booking.html.twig.
 */
class BookingViewBuilder extends EntityViewBuilder {

  /**
   * {@inheritdoc}
   */
  public function view(EntityInterface $entity, $view_mode = 'full', $langcode = NULL): array {
    $build               = parent::view($entity, $view_mode, $langcode);
    $build['#theme']     = 'booking';
    $build['#booking']   = $entity;
    $build['#view_mode'] = $view_mode;
    return $build;
  }

}
