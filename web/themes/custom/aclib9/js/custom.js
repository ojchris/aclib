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
  
    attach: function(context, settings) {
	
	  var self = this;
	  
	  $('.dropdown-toggle.tb-megamenu-no-link', context).each(function(index, element) {
	  
	    var submenu = $(element).next();
	    if (submenu.length && submenu.hasClass('tb-megamenu-submenu')) {
		  // Do action only for screens with burger menu.
	      if ($(window).width() < 980) {
		    self.toggleCollapsed($(element), submenu, index, context);
          }
          else {
	        $(element).removeAttr('role');
	        submenu.removeClass('visually-hidden');
	      }
        }
      });
    },
    
    toggleCollapsed: function(element, submenu, index, context) {
      var self = this;
      element.attr('role', 'button');
      submenu.addClass('visually-hidden').addClass('opacity-0').css('transition', 'opacity .3s ease-in');
      element.on('click', function(e) {
        submenu.toggleClass('visually-hidden');
        if (submenu.hasClass('visually-hidden')) {
          submenu.removeClass('opacity-100').addClass('opacity-0');
        }
        else {
          submenu.removeClass('opacity-0').addClass('opacity-100');
        }
        self.mapSiblings($(e.target).parent().siblings(), index, context);
      });
	},
	
	mapSiblings: function(siblings, index, context) {
   	  siblings.each(function(index, sibling) {
	    var siblingChild = $(sibling).find('.tb-megamenu-submenu');
	    if (siblingChild.length) {
          siblingChild.addClass('visually-hidden');
          siblingChild.removeClass('opacity-100').addClass('opacity-0');
	    } 
	  });
    }
  
  };

})(jQuery, Drupal);