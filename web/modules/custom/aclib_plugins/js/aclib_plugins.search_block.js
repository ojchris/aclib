/**
 * @file
 * Search block related actions
 */

(function ($, Drupal) { 

  'use strict';

  Drupal.behaviors.aclibSearchBlock = {
   
    attach: function(context, settings) { 
    
      // These are our search forms - to benefit of bootstrap classes (search-form) yet to target only our plugin (aclib-plugins class)
      $('.aclib-plugins.search-form').once('searchInputAction').each(function() {
        
        var submit = $(this).find('.form-submit');
        var href = submit.attr('href');
        var input = $(this).find('input[type=search]');

        // Listen for user inputs and change keyword token accordingly 
        input.on('change', function(e) {
          if ($(e.currentTarget).val()) {
            href = href.indexOf('%keyword') > -1 ? href.replace('%keyword', $(e.currentTarget).val()) : href;
            submit.attr('href', href);
          }
        });

        // Deal with enter 
        input.on('keydown', function(e) {
          if (e.keyCode === 13) {
            if ($(e.currentTarget).val()) {
              window.location.href = href.indexOf('%keyword') > -1 ? href.replace('%keyword', $(e.currentTarget).val()) : href;
            }
          }
        });
      });
    }
  };

})(jQuery, Drupal);