<?php

namespace Drupal\commerce_valitor\Ajax;

use Drupal\Core\Ajax\CommandInterface;
use Drupal\Core\Ajax\CommandWithAttachedAssetsTrait;

/**
 * Open popup window with content.
 */
class WindowOpenCommand implements CommandInterface {

  use CommandWithAttachedAssetsTrait;

  /**
   * The content for the dialog.
   *
   * Either a render array or an HTML string.
   *
   * @var string|array
   */
  protected $content;

  /**
   * Stores popup-specific options passed directly to JS.
   *
   * @var array
   */
  protected $windowOptions;

  /**
   * Custom settings passed to the Drupal behaviors in the dialog content.
   *
   * @var array
   */
  protected $settings;

  /**
   * Constructs an OpenDialogCommand object.
   *
   * @param string|array $content
   *   The content that will be placed in the dialog, either a render array
   *   or an HTML string.
   * @param array $window_options
   *   (optional) Options to be passed to the popup window.
   * @param array|null $settings
   *   (optional) Custom settings that will be passed to the Drupal behaviors
   *   on the content of the dialog. If left empty, the settings will be
   *   populated automatically from the current request.
   */
  public function __construct($content, array $window_options = [], $settings = NULL) {
    $this->content = $content;
    if (!empty($window_options)) {
      $options = [];
      foreach ($window_options as $key => $value) {
        $options[] = $key . "=" . $value;
      }
      $this->windowOptions = implode(',', $options);
    }
    $this->settings = $settings;
  }

  /**
   * Returns the window options.
   *
   * @return array
   *   The window options.
   */
  public function getWindowOptions() {
    return $this->windowOptions;
  }

  /**
   * Sets the widnow options array.
   *
   * @param array $window_options
   *   Options to be passed to the popup implementation.
   */
  public function setWindowOptions(array $window_options) {
    $this->windowOptions = $window_options;
  }

  /**
   * Sets a single window option value.
   *
   * @param string $key
   *   Key of the window option.
   * @param mixed $value
   *   Option to be passed to the window implementation.
   */
  public function setWindowOption($key, $value) {
    $this->windowOptions[$key] = $value;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    return [
      'command' => 'valitorWindowOpen',
      'data' => $this->getRenderedContent(),
      'settings' => $this->settings,
      'windowOptions' => $this->getWindowOptions(),
    ];
  }

}
