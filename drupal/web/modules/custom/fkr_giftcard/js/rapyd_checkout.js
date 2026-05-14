(function (Drupal, drupalSettings) {
  Drupal.behaviors.fkrRapydCheckout = {
    attach: function (context, settings) {
      var s = settings.fkrGiftcard;
      if (!s || !s.checkoutId) return;

      var container = context.querySelector('#rapyd-checkout');
      if (!container) return;

      var script = document.createElement('script');
      script.src = s.toolkitUrl;
      script.onload = function () {
        var checkout = new RapydCheckoutToolkit({
          id: s.checkoutId,
          close_url: s.cancelUrl,
          complete_url: s.completeUrl,
        });
        checkout.displayCheckout();
      };
      document.head.appendChild(script);
    },
  };
})(Drupal, drupalSettings);
