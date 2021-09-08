<?php

namespace Drupal\flickr_formatter\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\FormElement;

/**
 * Provides a form element with Flickr options.
 *
 * Usage example:
 * @code
 * $form['flickr_formatter'] = [
 *   '#type' => 'flickr_formatter_base',
 *   '#title' => t('Flickr_formatter base settings'),
 *   '#default_value' => (array) $values,
 * ];
 * @endcode
 *
 * @FormElement("flickr_formatter_base")
 */
class FlickrFormatterBase extends FormElement {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return [
      '#input' => TRUE,
      '#process' => [
        [__CLASS__, 'process'],
      ],
    ];
  }

  /**
   * Define and append children for this element.
   */
  public static function process(array &$element, FormStateInterface $form_state, array &$complete_form) {

    $flickr_formatter_service = \Drupal::service('flickr_formatter.service');

    $default_values = $flickr_formatter_service->getDefaultValues($element);
    $default_options = $flickr_formatter_service->getDefaultOptions();
    $size_options = $flickr_formatter_service->getSizesOptions();

    $element['title'] = [
      '#type' => 'textfield',
      '#title' => t('Title'),
      '#description' => t('Set some default title on top of parent container.'),
      '#default_value' => $default_values['title'],
    ];

    $element['size'] = [
      '#type' => 'select',
      '#title' => t('Image size'),
      '#description' => t('Select one of available Flickr image size presets.'),
      '#default_value' => $default_values['size'],
      '#options' => $size_options,
    ];

    $element['max_width'] = [
      '#type' => 'checkbox',
      '#title' => t('Max width'),
      '#description' => t('Automatically set max width for a parent container, based on Image size preset.'),
      '#default_value' => $default_values['max_width'],
    ];

    $element['link'] = [
      '#type' => 'checkbox',
      '#title' => t('Link image to Flickr page'),
      '#description' => t('Wrap each image with a link to its FLickr page.'),
      '#default_value' => $default_values['link'],
    ];

    $element['caption'] = [
      '#type' => 'checkbox',
      '#title' => t('Show image caption'),
      '#description' => t('It is a text of caption for each image returned from remote Flickr API.'),
      '#default_value' => $default_values['caption'],
    ];

    $element['classes'] = [
      '#type' => 'textfield',
      '#title' => t('CSS class'),
      '#description' => t('Add extra classes to parent element. Divide classes with space and without leading dot. I.e. <em>container theme-dark</em>'),
      '#default_value' => $default_values['classes'],
    ];

    $element['type'] = [
      '#type' => 'select',
      '#title' => t('Flickr Type'),
      '#description' => t('Type of Flickr media to render.'),
      '#default_value' => $default_values['type'],
      '#options' => $default_options['type'],
      '#id' => 'flickr-formatter-type',
    ];

    $element['per_page'] = [
      '#type' => 'number',
      '#title' => t('Items per page'),
      '#description' => t('Using API pager, set how many items per page. Leave empty for ALL in one response.'),
      '#default_value' => $default_values['per_page'],
      '#states' => [
        'invisible' => [
          ':input[id="flickr-formatter-type"]' => ['value' => 'photo'],
        ],
      ],
    ];

    $element['style'] = [
      '#type' => 'select',
      '#title' => t('Style'),
      '#description' => t('Choose style (theme) for group of photos.'),
      '#default_value' => $default_values['style'],
      '#options' => $default_options['style'],
      '#id' => 'flickr-formatter-style',
      '#states' => [
        'invisible' => [
          ':input[id="flickr-formatter-type"]' => ['value' => 'photo'],
        ],
      ],
    ];

    return $element;
  }

}
