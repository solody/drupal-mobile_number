/**
 * @file
 */

(function($) {
  Drupal.behaviors.mobileNumberFormElement = {
    attach: function (context, settings) {
      $('.mobile-number-field .local-number', context).each(function() {
        var $input = $(this);
        var val = $input.val();
        $input.keyup(function(e){
          if (val != $(this).val()) {
            val = $(this).val();
            $input.parents('.mobile-number-field').find('.send-button').addClass('show');
            $input.parents('.mobile-number-field').find('.verified').addClass('hide');
          }
        });
      });

      $('.mobile-number-field .country', context).each(function() {
        var $input = $(this);
        var val = $input.val();
        $input.change(function(e){
          if (val != $(this).val()) {
            val = $(this).val();
            $input.parents('.mobile-number-field').find('.send-button').addClass('show');
            $input.parents('.mobile-number-field').find('.verified').addClass('hide');
          }
        });
      });

      if (settings['mobileNumberVerificationPrompt']) {
        $('#' + settings['mobileNumberVerificationPrompt'] + ' .verification').addClass('show');
      }

      if (settings['mobileNumberVerified']) {
        $('#' + settings['mobileNumberVerified'] + ' .send-button').removeClass('show');
        $('#' + settings['mobileNumberVerified'] + ' .verified').addClass('show');
      }
    }
  };
})(jQuery);
