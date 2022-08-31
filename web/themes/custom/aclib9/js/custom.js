/**
 * @file
 * Global utilities.
 *
 */
(function($, Drupal) {

  'use strict';

  Drupal.behaviors.aclib9 = {
    attach: function(context, settings) {

      // Custom code here

    }
  };
     
  Drupal.behaviors.aclibTbMegaMenu = {
  
    /**
     * {@inheritdoc}
     *
     * Attach our own tb-megamenu behavior for tablets and phones.
     */
     attach: function(context, settings) {
	
	    var self = this;
	    
	    $('.dropdown-toggle.tb-megamenu-no-link', context).each(function(index, element) {
	  
	      var submenu = $(element).next();
	      if (submenu.length && submenu.hasClass('tb-megamenu-submenu')) {
		      // Do action only for screens with burger menu.
	        if ($(window).width() < 880) {
		        self.toggleCollapsed($(element), submenu, index, context);
          }
          else {
	          submenu.removeClass('visually-hidden');
	        }
        }
      });
    },
    
    /**
     * Main toggle callback, show/hide submenu,
     * show/hide siblings (so to produce accordion like effect).
     *
     * @param HTMLElement element
     *   Instance "span.dropdown-toggle".
     * @param HTMLElement submenu
     *   Instance of "div.tb-megamenu-submenu".
     * @param integer index
     *   Index of the array of toggle elements.
     * @param object context
     *   Drupal.behaviors provided context object.
     */
    toggleCollapsed: function(element, submenu, index, context) {
      var self = this;
      submenu.addClass('visually-hidden').addClass('opacity-0'); // .css('transition', 'opacity .3s ease-in');
  
      // Clicking on the most parent item (i.e. "Borrow and download")    
      element.on('click', function(e) {
        
        // Those icons are rendered in tb-megamenu-item.html.twig template.
        var offIcon = $(e.target).find('.caret-trigger-off');
        var onIcon = $(e.target).find('.caret-trigger-on');
        
        submenu.toggleClass('visually-hidden');
        if (submenu.hasClass('visually-hidden')) {
          submenu.removeClass('opacity-100').addClass('opacity-0');
          onIcon.addClass('visually-hidden');
          offIcon.removeClass('visually-hidden');
        }
        else {
          submenu.removeClass('opacity-0').addClass('opacity-100');
          onIcon.removeClass('visually-hidden');
          offIcon.addClass('visually-hidden');
        }
                
        self.mapSiblings($(e.target).parent().siblings(), index, context);
       
      });
	  },
	
	  /**
     * Handling siblings of a parent menu item.
     * 
     * @param array siblings
     *   An array of toggle's parent siblings objects,
     *   those should be the other "li.tb-megamenu-item" most likely.
     * @param integer index
     *   Index of the array of toggle elements.
     * @param object context
     *   Drupal.behaviors provided context object.
     */
    mapSiblings: function(siblings, index, context) {
   	  var self = this;
   	  setTimeout(function() {
     	  siblings.each(function(index, sibling) {
	        var siblingChild = $(sibling).find('.tb-megamenu-submenu');
	        if (siblingChild.length) {
            // Reset icons.
            var offIcon = $(sibling).find('.caret-trigger-off');
            var onIcon = $(sibling).find('.caret-trigger-on');
            offIcon.removeClass('visually-hidden');
            onIcon.addClass('visually-hidden');
            // Collapse sibling submenu.
            siblingChild.addClass('visually-hidden');
            siblingChild.removeClass('opacity-100').addClass('opacity-0');
	        } 
	      });
      }, 1);
    }
  };

})(jQuery, Drupal);