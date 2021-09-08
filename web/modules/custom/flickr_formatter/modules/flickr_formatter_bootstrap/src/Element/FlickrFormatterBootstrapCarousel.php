<?php

namespace Drupal\flickr_formatter_bootstrap\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\FormElement;

/**
 * Provides a form element with Bootstrap carousel options.
 *
 * Usage example:
 * @code
 * $form['bootstrap_carousel'] = [
 *   '#type' => 'flickr_formatter_bootstrap_carousel',
 *   '#title' => t('Bootsrap Carousel'),
 *   '#default_value' => [
 *     'interval' => $interval,
 *     'keyboard' => $keyboard,
 *     'ride' => FALSE,
 *     'navigation' => TRUE
 *     ...
 *     'effect' => 'slide',
 *   ],
 * ];
 * @endcode
 *
 * @FormElement("flickr_formatter_bootstrap_carousel")
 */
class FlickrFormatterBootstrapCarousel extends FormElement {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $element = [
      '#input' => TRUE,
      '#process' => [
        [__CLASS__, 'process'],
      ],
    ];
    return $element;
  }

  /**
   * Define and append children for this element.
   */
  public static function process(array &$element, FormStateInterface $form_state, array &$complete_form) {

    $default_values = \Drupal::service('flickr_formatter.service')->getDefaultValues($element);

    $element['description'] = [
      '#markup' => t('Implements <a href="https://getbootstrap.com/docs/5.1/components/carousel/#options" target="_blank">Bootstrap 5 Carousel options</a>'),
    ];

    $element['ride'] = [
      '#type' => 'checkbox',
      '#title' => t('Ride (Autoplay)'),
      '#description' => t('Autoplays the carousel after the user manually cycles the first item.'),
      '#default_value' => $default_values['ride'],
      '#id' => 'flickr-formatter-bootstrap-ride',
    ];

    $element['interval'] = [
      '#type' => 'number',
      '#title' => t('Interval'),
      '#description' => t('The amount of time to delay between automatically cycling an item (in milliseconds). If false, carousel will not automatically cycle.'),
      '#default_value' => is_numeric($default_values['interval']) ? $default_values['interval'] : 5000,
      '#states' => [
        'visible' => [
          ':input[id="flickr-formatter-bootstrap-ride"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $element['keyboard'] = [
      '#type' => 'checkbox',
      '#title' => t('Keyboard'),
      '#description' => t('Whether the carousel should react to keyboard events.'),
      '#default_value' => $default_values['keyboard'],
    ];

    $element['navigation'] = [
      '#type' => 'checkbox',
      '#title' => t('Show navigation'),
      '#default_value' => $default_values['navigation'],
    ];

    $element['indicators'] = [
      '#type' => 'checkbox',
      '#title' => t('Show indicators'),
      '#default_value' => $default_values['indicators'],
    ];

    $element['pause'] = [
      '#type' => 'checkbox',
      '#title' => t('Pause on hover'),
      '#description' => t('Pauses the cycling of the carousel on mouseenter and resumes the cycling of the carousel on mouseleave.'),
      '#default_value' => $default_values['pause'],
    ];

    $element['wrap'] = [
      '#type' => 'checkbox',
      '#title' => t('Wrap'),
      '#description' => t('Whether the carousel should cycle continuously or have hard stops.'),
      '#default_value' => $default_values['wrap'],
    ];

    $element['effect'] = [
      '#type' => 'select',
      '#title' => t('Effect'),
      '#description' => t('<a href="https://getbootstrap.com/docs/5.1/components/carousel/#crossfade" target="_blank">Transition effect</a>'),
      '#empty_option' => t('No effect'),
      '#options' => [
        'slide' => t('Slide'),
        'slide carousel-fade' => t('Fade'),
      ],
      '#default_value' => $default_values['effect'],
    ];

    return $element;
  }

}
