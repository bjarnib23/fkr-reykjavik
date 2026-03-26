/**
 * @file
 * Javascript to generate Stripe token in PCI-compliant way.
 */

(function ($, Drupal, drupalSettings) {

  'use strict';

  let win = false;

  /**
   * Track the window pop up close event.
   */
  let timer = setInterval(function () {
    if (win && win.closed) {
      // If user has closed the window, remove overlay.
      window.document.body.classList.remove("non-clickable");
    }
  }, 1000);

  function runScripts(win) {
    var scripts;

    // Get the scripts
    scripts = win.document.body.getElementsByTagName('script');
    // Run them in sequence (remember NodeLists are live)
    continueLoading();
    function continueLoading() {
      var script, newscript;
      // While we have a script to load...
      while (scripts.length) {
        // Get it and remove it from the DOM
        script = scripts[0];
        script.parentNode.removeChild(script);
        // Create a replacement for it
        newscript = win.document.createElement('script');
        // External?
        if (script.src) {
          // Yes, we'll have to wait until it's loaded before continuing
          newscript.onerror = continueLoadingOnError;
          newscript.onload = continueLoadingOnLoad;
          newscript.onreadystatechange = continueLoadingOnReady;
          newscript.src = script.src;
        } else {
          // No, we can do it right away
          newscript.text = script.text;
        }

        // Start the script
        win.document.documentElement.appendChild(newscript);

        // If it's external, wait for callback
        if (script.src) {
          return;
        }
      }

      // All scripts loaded
      newscript = undefined;

      // Callback on most browsers when a script is loaded
      function continueLoadingOnLoad() {
        // Defend against duplicate calls
        if (this === newscript) {
          continueLoading();
        }
      }

      // Callback on most browsers when a script fails to load
      function continueLoadingOnError() {
        // Defend against duplicate calls
        if (this === newscript) {
          continueLoading();
        }
      }

      // Callback on IE when a script's loading status changes
      function continueLoadingOnReady() {
        // Defend against duplicate calls and check whether the
        // script is complete (complete = loaded or error)
        if (this === newscript && this.readyState === 'complete') {
          continueLoading();
        }
      }
    }
  }

  /**
   * Ajax command valitorWindowOpen.
   * @param ajax
   * @param response
   * @param status
   */
  Drupal.AjaxCommands.prototype.valitorWindowOpen = function (ajax, response, status) {
    if (!win) {
      win = window.open('', 'valitor_secure_processor', response.windowOptions);
    }
    win.document.body.innerHTML = response.data;
    runScripts(win);
    win.focus();
  }

  /**
   * Ajax command valitorWindowOpen.
   * @param ajax
   * @param response
   * @param status
   */
  Drupal.AjaxCommands.prototype.valitorWindowClose = function (ajax, response, status) {
    if (win) {
      win.close();
    }
  }

  /**
   * Ask for confirmation before closing the main window.
   */
  window.addEventListener("beforeunload", function (e) {
    // Check if pop-up window is there.
    if (!win || win.closed) {
      return;
    }
    window.blur();
    win.focus();
    // Cancel the event
    e.preventDefault(); // If you prevent default behavior in Mozilla Firefox prompt will always be shown
    // Chrome requires returnValue to be set
    e.returnValue = "";
  });

  /**
   * Process the 3DS values pushed from popup window after card verification.
   */
  window.addEventListener("message", function (event) {
    // Do we trust the sender of this message?
    if (event.origin !== window.location.origin) {
      return;
    }
    if (event.data === '' || event.data.length === 0) {
      return;
    }
    // Set payment method form values.
    $.each(event.data, function (key, value) {
      $(key).val(value);
    });
    let dialog = $('.ui-dialog');
    if (dialog.length > 0) {
      // Close the dialog.
      dialog.remove();
    }
    // Submit checkout form.
    $('.valitor-card-form').each(function () {
      let $form = $(this).closest('form');
      $form.submit();
    });
  }, false);

  /**
   * Attaches the commerceStripeForm behavior.
   *
   * @type {Drupal~behavior}
   *
   * @prop object cardNumber
   *   Stripe card number element.
   * @prop object cardExpiry
   *   Stripe card expiry element.
   * @prop object cardCvc
   *   Stripe card cvc element.
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the commerceStripeForm behavior.
   * @prop {Drupal~behaviorDetach} detach
   *   Detaches the commerceStripeForm behavior.
   *
   * @see Drupal.commerceStripe
   */
  Drupal.behaviors.commerceValitorForm = {

    attach: function (context, settings) {
      if (!settings.commerceValitor || !settings.commerceValitor.verify_url) {
        return;
      }
      const forms = once('valitor-processed', '.valitor-card-form', context);
      forms.forEach(function (value, index) {
        var $form = $(value).closest('form');

        // Form submit.
        $form.on('submit', function (e) {
          if ($('#valitor-ds_trans_id').val() === '') {
            e.preventDefault();
            let verify_payload = {
              'cardNumber': $('.valitor-card-number', $form).val(),
              'expirationMonth': $('.valitor-card-expiration-month', $form).val(),
              'expirationYear': $('.valitor-card-expiration-year', $form).val(),
              'cvc': $('.valitor-card-code', $form).val(),
              'order_id': settings.commerceValitor.order_id,
              'order_total_amount': settings.commerceValitor.order_total_amount
            }
            $('body').addClass('non-clickable');
            // Create the popup window now to avoid blocking by browser.
            win = window.open(settings.commerceValitor.popup_url, 'valitor_secure_processor', 'width=700,height=600,top=100,left=100');
            win.onload = function () {
              if (win.location.href === settings.commerceValitor.popup_url) {
                Drupal.ajax({url: settings.commerceValitor.verify_url, submit: verify_payload}).execute();
              }
            };
            return false;
          }
        });
      });
    },

    detach: function (context, settings, trigger) {
      if (trigger !== 'unload') {
        return;
      }
      var $form = $('.valitor-card-form', context).closest('form');
      if ($form.length === 0) {
        return;
      }
      $form.off('submit.valitor-processed');
    }
  };

})(jQuery, Drupal, drupalSettings);
