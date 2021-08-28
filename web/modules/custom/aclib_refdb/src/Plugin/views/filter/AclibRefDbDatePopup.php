<?php

namespace Drupal\aclib_refdb\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Datetime\DateHelper;
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
   * Start and end dates labels defined static and only once.
   */
  const MIN_MAX_LABELS = [
    'min' => 'Start date',
    'max' => 'End date',
  ];

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
        $this->buildDateProperties($element['min'], $this->value['min'], static::MIN_MAX_LABELS['min']);
        $this->buildDateProperties($element['max'], $this->value['max'], static::MIN_MAX_LABELS['max']);

        if (isset($element['value'])) {
          $this->buildDateProperties($element['value'], $this->value['value']);
        }
      }
      else {
        // Set our properties.
        // Note - related drupal core error notice perhaps can be produced.
        // @see: https://www.drupal.org/project/drupal/issues/2825860.
        $this->buildDateProperties($element, $this->value['value']);
      }
    }
  }

  /**
   * Override parent method, which deals with dates as integers.
   *
   * Note - setting on exposed view filter MUST be "-1 month"
   * for BOTH inputs Min and Max.
   *
   * {@inheritdoc}
   */
  protected function opBetween($field) {
    $timezone = $this->getTimezone();
    $origin_offset = $this->getOffset($this->value['min'], $timezone);

    // Although both 'min' and 'max' values are required, default empty 'min'
    // value as UNIX timestamp 0.
    $min = (!empty($this->value['min'])) ? $this->value['min'] : '@0';

    // Convert to ISO format and format for query. UTC timezone is used since
    // dates are stored in UTC.
    $a = new DrupalDateTime($min, new \DateTimeZone($timezone));
    $a = $this->query->getDateFormat($this->query->getDateField("'" . $this->dateFormatter->format($a->getTimestamp() + $origin_offset, 'custom', DateTimeItemInterface::DATETIME_STORAGE_FORMAT, DateTimeItemInterface::STORAGE_TIMEZONE) . "'", TRUE, $this->calculateOffset), $this->dateFormat, TRUE);

    // **** Here is the only change compared with the original method.
    $b = new DrupalDateTime($this->value['max'] . 'T23:59:59', new \DateTimeZone($timezone));
    $b = $this->query->getDateFormat($this->query->getDateField("'" . $this->dateFormatter->format($b->getTimestamp() + $origin_offset, 'custom', DateTimeItemInterface::DATETIME_STORAGE_FORMAT, DateTimeItemInterface::STORAGE_TIMEZONE) . "'", TRUE, $this->calculateOffset), $this->dateFormat, TRUE);

    // This is safe because we are manually scrubbing the values.
    $operator = strtoupper($this->operator);
    $field = $this->query->getDateFormat($this->query->getDateField($field, TRUE, $this->calculateOffset), $this->dateFormat, TRUE);
    $this->query->addWhereExpression($this->options['group'], "$field $operator $a AND $b");
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

    // A special case for min/max "between" operator.
    // Note - setting on exposed view filter MUST be "-1 month"
    // for BOTH inputs Min and Max.
    if (in_array($title, array_values(static::MIN_MAX_LABELS)) && $value == '-1 month') {

      if ($date_object = new DrupalDateTime($value, new \DateTimeZone($timezone))) {
        $year = $this->dateFormatter->format($date_object->getTimestamp() + $origin_offset, 'custom', 'Y', DateTimeItemInterface::STORAGE_TIMEZONE);
        $month = $this->dateFormatter->format($date_object->getTimestamp() + $origin_offset, 'custom', 'm', DateTimeItemInterface::STORAGE_TIMEZONE);
        $day_in_month = '01';

        if ($title == 'End date') {
          $day_in_month = DateHelper::daysInMonth($date_object);
        }

        $element['#default_value'] = $year . '-' . $month . '-' . $day_in_month;
      }
    }
    // The other operators OR "between" operator but the one that does not have
    // both min and max "-1 month" since that is kind of a hardcode.
    else {
      if ($date_object = new DrupalDateTime($value, new \DateTimeZone($timezone))) {
        $element['#default_value'] = $this->dateFormatter->format($date_object->getTimestamp() + $origin_offset, 'custom', DateTimeItemInterface::DATE_STORAGE_FORMAT, DateTimeItemInterface::STORAGE_TIMEZONE);
      }
    }

  }

}
