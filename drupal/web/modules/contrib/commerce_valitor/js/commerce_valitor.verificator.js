(function ($, Drupal, drupalSettings) {

  'use strict';

  /**
   * Finish card verification process by pushing 3DS parameters from
   * popup window to main window.
   *
   * @type {{attach: Drupal.behaviors.commerceValitor3dVerification.attach}}
   */
  Drupal.behaviors.commerceValitor3dVerification = {
    attach: function (context, settings) {
      if ($('.valitor-3d-verification', context).length === 0) {
        return;
      }
      if (!$.isEmptyObject(settings.valitor)) {
        window.opener.document.body.classList.remove("non-clickable");
        window.close();
        // Push 3DS parameters back to the main window and close popup.
        window.opener.postMessage(settings.valitor, window.location.origin);
      }
    }
  }

})(jQuery, Drupal, drupalSettings);
