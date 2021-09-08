<?php

namespace Drupal\flickr_formatter_bootstrap\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\FormElement;

/**
 * Provides a form element with Bootstrap grid options.
 *
 * Usage example:
 * @code
 * $form['bootstrap_grid'] = [
 *   '#type' => 'flickr_formatter_bootstrap_carousel',
 *   '#title' => t('Bootsrap Grid'),
 *   '#default_value' => [
 *     'xs' => 'col',
 *     'sm' => 'col-auto',
 *     'md' => 'col-2',
 *     'lg' => 'col-4',
 *     'xl' => 'col-4',
 *     'xxl' => 'col-6',
 *   ],
 * ];
 * @endcode
 *
 * @FormElement("flickr_formatter_bootstrap_grid")
 */
class FlickrFormatterBootstrapGrid extends FormElement {

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

    $element['description'] = [
      '#markup' => t('Implements <a href="https://getbootstrap.com/docs/5.1/layout/grid/#grid-options" target="_blank">Bootstrap 5 Grid options</a>.'),
    ];

    $default_options = \Drupal::service('config.factory')->get('flickr_formatter_bootstrap.settings')->getRawData();

    if (!isset($default_options['bootstrap_grid']) || empty($default_options['bootstrap_grid'])) {
      return $element;
    }

    if (isset($default_options['_core'])) {
      unset($default_options['_core']);
    }

    if (isset($default_options['bootstrap_grid']['options']) && !empty($default_options['bootstrap_grid']['options'])) {

      $default_values = \Drupal::service('flickr_formatter.service')->getDefaultValues($element);
      $translation_manager = \Drupal::service('string_translation');

      foreach ($default_options['bootstrap_grid']['options'] as $breakpoint => $breakpoint_label) {
        $prefix = 'col' . ($breakpoint != 'xs' ? '-' . $breakpoint : '');
        $element[$breakpoint] = [
          '#type' => 'select',
          '#title' => t('Column width at @breakpoint breakpoint', ['@breakpoint' => $breakpoint]),
          '#default_value' => $default_values[$breakpoint],
          '#description' => t('Set the number of columns each item should take up at the @breakpoint breakpoint and higher. If "None" it will inherit from previous.', ['@breakpoint' => $breakpoint]),
          '#empty_option' => t('None'),
          '#options' => [],
        ];

        $element[$breakpoint]['#options'][$prefix] = t('Equal');
        $element[$breakpoint]['#options'][$prefix . '-auto'] = t('Fit to content');

        $columns = [1, 2, 3, 4, 6, 12];
        foreach ($columns as $width) {
          $element[$breakpoint]['#options'][$prefix . '-' . $width] = $translation_manager->formatPlural(12 / $width, '@width (@count column per row)', '@width (@count columns per row)', ['@width' => $width]);
        }
      }
    }
    return $element;
  }

}
