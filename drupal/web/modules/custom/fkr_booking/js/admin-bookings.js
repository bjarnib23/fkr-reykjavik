(function ($, Drupal, once) {
  Drupal.behaviors.adminBookings = {
    attach: function (context, settings) {
      once('booking-status', '.booking-status-select', context).forEach(function (el) {
        // Set initial color.
        setColor(el, el.value);

        el.addEventListener('change', function () {
          var nid = el.dataset.nid;
          var status = el.value;

          $.get('/session/token').done(function (token) {
            $.ajax({
              url: '/admin/fkr/bookings/' + nid + '/status',
              method: 'POST',
              contentType: 'application/json',
              headers: { 'X-CSRF-Token': token },
              data: JSON.stringify({ status: status }),
              success: function () {
                setColor(el, status);
              },
              error: function () {
                alert('Failed to update status.');
              }
            });
          });
        });
      });

      function setColor(el, status) {
        var colors = {
          pending: '#fff3cd',
          confirmed: '#d4edda',
          rejected: '#f8d7da'
        };
        el.style.backgroundColor = colors[status] || '#fff';
        el.style.borderRadius = '4px';
        el.style.padding = '4px 8px';
        el.style.border = '1px solid #ccc';
        el.style.cursor = 'pointer';
      }
    }
  };
})(jQuery, Drupal, once);
