(function ($, Drupal, once) {
  Drupal.behaviors.localgovConsultationToggle = {
    attach: function (context, settings) {
      const elements = once('toggle-consultation', '#btn-toggle-consultation', context);

      $(elements).on('click', function () {
        $('#localgov-consultation-body').toggleClass('consultation--is-complete');
      });
    }
  };
})(jQuery, Drupal, once);
