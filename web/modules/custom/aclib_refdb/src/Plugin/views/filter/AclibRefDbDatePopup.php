<?php

namespace Drupal\aclib_refdb\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\datetime\Plugin\views\filter\Date;

/**
 * Transform Datetime views filter form element into HTMl5 date element.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("aclib_refdb_datetime")
 */
class AclibRefDbDatePopup extends Date {

  /**
   * {@inheritdoc}
   */
  public function buildExposedForm(&$form, FormStateInterface $form_state) {

    parent::buildExposedForm($form, $form_state);

    if (!empty($this->options['expose']['identifier'])) {
      $identifier = $this->options['expose']['identifier'];
      // Identify wrapper.
      $wrapper_key = $identifier . '_wrapper';
      if (isset($form[$wrapper_key])) {
        $element = &$form[$wrapper_key][$identifier];
      }
      else {
        $element = &$form[$identifier];
      }
      // Detect filters that are using min/max.
      if (isset($element['min'])) {

        $element['min']['#type'] = 'date';
        $element['min']['#attributes']['type'] = 'date';
        $element['min']['#title'] = t('Start date');

        $element['max']['#type'] = 'date';
        $element['max']['#attributes']['type'] = 'date';
        $element['max']['#title'] = t('End date');

        if (isset($element['value'])) {
          $element['value']['#type'] = 'date';
          $element['value']['#attributes']['type'] = 'date';
        }
      }
      else {
        $element['#type'] = 'date';
        $element['#attributes']['type'] = 'date';
      }
    }
  }

}
