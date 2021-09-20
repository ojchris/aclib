/**
 * @file
 * Bootstrap Flickr formatter plugin.
 */

(function ($, Drupal, CKEDITOR) {

  "use strict";

  var methods = {
  
    getExisting: function(editor, values) {
      var selection = editor.getSelection();
      var selectedElement = selection.getStartElement();
      
      if (selectedElement && selectedElement.hasClass('cke_widget_wrapper_flickr-formatter-inline')) {
        return selectedElement;
      }
      return this.findWrapper(selectedElement); 

    },
    
    findWrapper: function(element) {
      return element.getAscendant(function (el) {
      if (typeof el.hasClass === 'function') {
        return el.hasClass('cke_widget_wrapper_flickr-formatter-inline');
      }
      return false;
      }, true);
    },

    parseAttributes: function(editor, element) {
      var parsedAttributes = {};
      var domElement = $(element.$).find('.flickr-formatter-inline > div')[0];
      var attribute;
      var attributeName;

      for (var attrIndex = 0; attrIndex < domElement.attributes.length; attrIndex++) {
        attribute = domElement.attributes.item(attrIndex);
        attributeName = attribute.nodeName.toLowerCase();

        if (attributeName.indexOf('data-cke-') === 0) {
          continue;
        }
        parsedAttributes[attributeName] = element.data("cke-saved-".concat(attributeName)) || attribute.nodeValue;
      }

      if (parsedAttributes.class) {
        parsedAttributes.class = CKEDITOR.tools.trim(parsedAttributes.class.replace(/cke_\S+/, ''));
      }

      return parsedAttributes;
    }
  };

  CKEDITOR.plugins.add('flickr_formatter_bs_grid', {
    requires: 'widget',
    icons: 'flickr_formatter_bs_grid',
    init: function (editor) {

      // Allow widget editing.
      editor.widgets.add('flickr_formatter_bs_grid_widget', {
        //template: '<div class="flickr-formatter-inline"></div>',
        allowedContent: '',
        requiredContent: 'div(flickr-formatter-inline)',
        upcast: function (element) {
          return element.name === 'div' && element.hasClass('flickr-formatter-inline');
        },
        /*
        init: function () {
        }
        */
      });

      // Add the dialog command.
      editor.addCommand('flickr_formatter_bs_grid', {
        //allowedContent: 'div[class, data-*]',
        allowedContent: {
          div: {
            attributes: {
             '!data-placeholder': true,
            },
            classes: {}
          },
          span: {
            attributes: {
             '!data-placeholder': true,
            },
            classes: {}
          }
        },

        requiredContent: 'div[class, data-*]',
        modes: {wysiwyg: 1},
        canUndo: true,
        exec: function (editor) {
          var existingValues = {};
          var existingElement = methods.getExisting(editor);
          
          if (existingElement) {

            var attributes = methods.parseAttributes(editor, existingElement);
            existingValues.flickr_id = attributes['data-flickr-id'];

            existingValues.flickr_base = {
              'title': attributes['data-title'],
              'size': attributes['data-size'],
              'max_width': attributes['data-max-width'],
              'link': attributes['data-link'],
              'caption': attributes['data-caption'],
              'classes': attributes['data-classes'],
              'type': attributes['data-type'],
              'style': attributes['data-style']
            };

            if (attributes['style'] && attributes['style'].indexOf('max-width') > -1) {
              existingValues.flickr_base.max_width = 1;
            }

            var classes = attributes.class ? attributes.class.split(' ') : [];

            if (classes.indexOf('carousel') > -1) {

              existingValues.bootstrap_carousel = {
                value: {}
              };
              $.each(attributes, function(key, value) {
                if (key.indexOf('data-bs-') > -1) {
                  var originalKey = key.replace('data-bs-', '');
                  if (originalKey === 'interval') {
                    
                  }
                  existingValues.bootstrap_carousel.value[originalKey] = value;
                }
                else {
                  var originalKey = key.replace('-', '_').replace('data_', '');
                  existingValues.bootstrap_carousel.value[originalKey] = value;
                }
              });
            }

            if (classes.indexOf('grid') > -1) {
              existingValues.bootstrap_grid = {
                value: {}
              };
              $.each(attributes, function(key, value) {
                var originalKey = key.replace('data-', '');
                existingValues.bootstrap_grid.value[originalKey] = value;
              });
            }
          }

          // Fired when saving the dialog.
          var saveCallback = function (values) {

            editor.fire('saveSnapshot');

            if (values.flickr_base && values.flickr_base.style) {
             
              editor.insertHtml(values.style);

              editor.fire('saveSnapshot');
            } 
          }
          var dialogSettings = {
            title: 'Flickr formatter',
            dialogClass: 'flickr-formatter-inline-dialog',
          };

          // Open the flickr dialog for corresponding button.
          Drupal.ckeditor.openDialog(editor, Drupal.url('flickr_formatter_bootstrap/dialog'), existingValues, saveCallback, dialogSettings);
        }
      });

      // UI Button
      editor.ui.addButton('flickr_formatter_bs_grid', {
        label: 'Insert Flickr',
        command: 'flickr_formatter_bs_grid',
        icon: this.path + 'icons/flickr_formatter_bs_grid.png'
      });

      // Context menu to edit existing.
      if (editor.contextMenu) {
        editor.addMenuGroup('flickrGroup');
        editor.addMenuItem('flickrItem', {
          label: 'Edit Flickr',
          icon: this.path + 'icons/flickr_formatter_bs_grid.png',
          command: 'flickr_formatter_bs_grid',
          group: 'flickrGroup'
        });

        // Load existing.
        editor.contextMenu.addListener(function (element) {
          if (methods.findWrapper(element)) {
            return {flickrItem: CKEDITOR.TRISTATE_OFF};
          }
        });
      }
    }
  });

})(jQuery, Drupal, CKEDITOR);
