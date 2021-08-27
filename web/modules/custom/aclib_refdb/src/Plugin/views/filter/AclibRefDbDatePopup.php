<?php

namespace Drupal\aclib_refdb\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\datetime\Plugin\views\filter\Date;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;

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
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    $form['expose']['description']['#maxlength'] = 256;
  }

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

        // Set our properties.
        $this->buildDateProperties($element['min'], $this->value['min'], 'Start date');
        $this->buildDateProperties($element['max'], $this->value['max'], 'End date');

        if (isset($element['value'])) {
          $this->buildDateProperties($element['value'], $this->value['value']);
        }
      }
      else {
        // Set our properties.
        // Note - there may be unrelated, drupal core bug/error notice.
        // @see: https://www.drupal.org/project/drupal/issues/2825860.
        $this->buildDateProperties($element, $this->value['value']);
      }
    }
  }

  /**
   * Set HTML5 date properties and default value on date form element.
   *
   * @param array $element
   *   Date form element we are manipulating.
   * @param string $value
   *   Current date filter value.
   * @param mixed $title
   *   (Optional) to change a form element #title (i.e. for min/max inputs).
   */
  protected function buildDateProperties(array &$element, string $value, $title = NULL) {
    $value = !empty($value) ? $value : 'now';
    $element['#type'] = 'date';
    $element['#attributes']['type'] = 'date';
    if ($title) {
      $element['#title'] = t('@title', ['@title' => $title]);
    }

    $timezone = $this->getTimezone();
    $origin_offset = $this->getOffset($value, $timezone);
    if ($date_object = new DrupalDateTime($value, new \DateTimeZone($timezone))) {
      $element['#default_value'] = $this->dateFormatter->format($date_object->getTimestamp() + $origin_offset, 'custom', DateTimeItemInterface::DATE_STORAGE_FORMAT, DateTimeItemInterface::STORAGE_TIMEZONE);
    }
  }

}
