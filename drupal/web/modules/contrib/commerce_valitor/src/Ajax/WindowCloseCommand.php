<?php

namespace Drupal\commerce_valitor\Ajax;

use Drupal\Core\Ajax\CommandInterface;

/**
 * Open popup window with content.
 */
class WindowCloseCommand implements CommandInterface {

  /**
   * {@inheritdoc}
   */
  public function render() {
    return [
      'command' => 'valitorWindowClose',
    ];
  }

}
