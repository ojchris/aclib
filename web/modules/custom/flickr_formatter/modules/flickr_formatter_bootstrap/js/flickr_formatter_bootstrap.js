/**
 * @file
 * Flickr formatter bootstrap utilities.
 *
 */
(function($, Drupal) {

  'use strict';
  
  Drupal.behaviors.aclibCarousel = {
   
    /**
     * Calculate carousel container height, based on tallest image.
     * Set it as CSS root variable to be used in scss.
     */
    attach: function(context, settings) {
      $('[data-flickr-id]', context).each(function(index, element) {
        var height = 0;
        var r = document.querySelector(':root');
        $(this).find('.carousel-inner > .carousel-item img').each(function(i, e) {
          if (e.height > height) {
            height = e.height;
          }
        });
        r.style.setProperty('--slide-height', height + 'px');
      });
    }
  };
  
})(jQuery, Drupal);