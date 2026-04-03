(function ($, Drupal, once) {
  Drupal.behaviors.availabilityCalendar = {
    attach: function (context, settings) {
      once('availability', '.cal-clickable', context).forEach(function (el) {
        el.addEventListener('click', function () {
          var datetime = el.dataset.datetime;

          $.get('/session/token').done(function (token) {
            $.ajax({
              url: '/api/fkr/availability/toggle',
              method: 'POST',
              contentType: 'application/json',
              headers: { 'X-CSRF-Token': token },
              data: JSON.stringify({ datetime: datetime }),
              success: function (response) {
                if (response.status === 'available') {
                  el.className = el.className.replace('cal-closed', 'cal-available');
                  el.querySelector('span').textContent = 'Available';
                } else {
                  el.className = el.className.replace('cal-available', 'cal-closed');
                  el.querySelector('span').textContent = 'Closed';
                }
              },
              error: function (xhr) {
                var msg = xhr.responseJSON ? xhr.responseJSON.error : 'Error';
                alert(msg);
              }
            });
          });
        });
      });
    }
  };
})(jQuery, Drupal, once);
