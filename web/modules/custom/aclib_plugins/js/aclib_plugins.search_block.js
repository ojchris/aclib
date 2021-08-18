/**
 * @file
 * Search block related actions
 */

(function ($, Drupal) { 

  'use strict';

  Drupal.behaviors.aclibSearchBlock = {
   
    attach: function(context, settings) { 

      var self = this;

      // These are our search forms - to benefit of bootstrap classes (search-form) yet to target only our plugin (aclib-plugins class)
      $('.aclib-plugins.search-form').once('searchInputAction').each(function() {
        
        var submit = $(this).find('.form-submit');
        var input = $(this).find('input[type=search]');

        // Listen for user inputs and change keyword token accordingly 
        input.on('change', function(e) {
          self.hrefValue(input, submit);
        });

        // Listen for user inputs and change keyword token accordingly 
        submit.on('click', function(e) {
          self.hrefValue(input, submit);
        });

        // Deal with enter 
        input.on('keydown', function(e) {
          if (e.keyCode === 13) {
            var href = self.hrefValue(input, submit);
            if (href) {
              window.location.href = href;
            }
          }
        });
      });

    },
    
    // A custom common method to transform href attribute
    hrefValue: function(input, submit) {
      if (input.val()) {
        var href = submit.attr('href');
        href = href.indexOf('%keyword') > -1 ? href.replace('%keyword', input.val()) : href;
        submit.attr('href', href);
        return href;
      }
    }    
    
  };

})(jQuery, Drupal);
