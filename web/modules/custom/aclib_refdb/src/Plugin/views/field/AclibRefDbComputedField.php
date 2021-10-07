<?php

namespace Drupal\aclib_refdb\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Datetime\DrupalDateTime;

use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ViewExecutable;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\ResultRow;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;

/**
 * Field handler for properties calculations.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("aclib_refdb_computed_field")
 */
class AclibRefDbComputedField extends FieldPluginBase {

  /**
   * Our own service instance.
   *
   * @var \Drupal\aclib_refdb\AclibRefdbService
   */
  protected $aclibService;

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL) {
    parent::init($view, $display, $options);
    $this->aclibService = \Drupal::service('aclib_refdb.main');
  }

  /**
   * Leave empty to avoid a query on this field that is fully computed.
   *
   * @{inheritdoc}
   */
  public function query() {
    $this->addAdditionalFields('nid');
  }

  /**
   * Define the available options.
   *
   * @{inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['property'] = ['default' => NULL];
    return $options;
  }

  /**
   * Provide the options form.
   *
   * @{inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {

    $options = $this->aclibService->defaultCountOptions(TRUE);
    $patterns = $this->aclibService->getPatterns('Pattern matched');

    $form['property'] = [
      '#title' => $this->t('Counting property'),
      '#type' => 'select',
      '#default_value' => $this->options['property'],
      '#options' => $options + $patterns,
      '#empty_option' => $this->t('- Select -'),
    ];
    parent::buildOptionsForm($form, $form_state);
  }

  /**
   * Render row/result.
   *
   * @{inheritdoc}
   */
  public function render(ResultRow $values) {

    $nid = is_object($values->_entity) && $values->_entity->hasField('nid') && !empty($values->_entity->get('nid')->getValue()) ? $values->_entity->get('nid')->getValue()[0]['value'] : NULL;
    if (!$nid) {
      return $this->t('Please add NID field for aclib_refdb_logs');
    }

    $query_count = NULL;
    $default_count_options = $this->aclibService->defaultCountOptions();
    $patterns = $this->aclibService->getPatterns('pattern_matched');
    $count_data = $default_count_options + $patterns;

    $datetime = $this->getParams();

    foreach ($count_data as $base_field => $options) {
      foreach ($options as $option_key => $option_label) {
        if ($this->options['property'] == $option_key) {
          $value = $option_key == 'overall' ? NULL : $option_key;
          $query_count = $this->aclibService->defaultCountQuery($base_field, $nid, $datetime, $value);
        }
      }
    }
    return $query_count ? $query_count->execute() : $this->t('No results found || Error');
  }

  /**
   * {@inheritdoc}
   */
  public function clickSort($order) {
    if (isset($this->field_alias) && isset($this->options['property'])) {

      $params = $this->options['group_type'] != 'group' ? ['function' => $this->options['group_type']] : [];

      $datetime = $this->getParams();

      $table = $this->view->storage->get('base_table');

      // Set first logic for Pattern matched type of fields, a special case.
      if (strpos($this->options['property'], '*') !== FALSE) {
        $patterns = $this->aclibService->getPatterns();
        if (!empty($patterns)) {
          foreach ($patterns as $pattern) {
            if ($pattern == $this->options['property']) {
              $formula = $this->aclibService->queryCountProperty('pattern_matched', $datetime, $pattern);
              $pattern_clean = 'patterns_' . str_replace('*', '', $pattern);
              $this->query->addOrderBy(NULL, $formula, $order, $pattern_clean, $params);
            }
          }
        }
      }
      // Here are the other "standard" fields, like location.
      else {
        $internals = $this->aclibService->defaultCountOptions();
        foreach ($internals as $property_key => $property) {
          if (in_array($this->options['property'], array_keys($property))) {
            // $value = $this->options['property'] == 'overall' ? NULL : $this->options['property'];
            $formula = $this->aclibService->queryCountProperty($property_key, $datetime, $this->options['property']);
            // $this->query->addField(NULL, $formula, 'count_property');
            // $this->query->groupby = [];
            // $this->query->addOrderBy(NULL, 'count_property', $order, 'count_property', ['function' => 'SUM', 'aggregate' => TRUE]);
            $this->query->addOrderBy(NULL, $formula, $order, $this->options['property']);
          }
        }
      }
      // $this->query->addTag('debug');
    }
  }

  /**
   * Get Views filters values.
   *
   * @return array
   *   An array containing start and end date strings.
   */
  protected function getParams() {
    $datetime = [];
    if (isset($_POST['datetime']) && isset($_POST['datetime']['min']) && isset($_POST['datetime']['max'])) {
      $min = $_POST['datetime']['min'] . 'T04:00:00';
      $max = $_POST['datetime']['max'] . 'T23:59:59';
    }
    else {
      $args = $this->aclibService->requestStack->query->all();
      if (isset($args['datetime']) && isset($args['datetime']['min']) && isset($args['datetime']['max'])) {
        $min = $args['datetime']['min'] . 'T04:00:00';
        $max = $args['datetime']['max'] . 'T23:59:59';
      }
    }

    $default_timezone = $this->aclibService->config->get('system.date')->get('timezone');
    $timezone = isset($default_timezone['default']) ? $default_timezone['default'] : $this->aclibService::DEFAULT_TIMEZONE;

    $start_datetime = new DrupalDateTime($min, new \DateTimeZone($timezone));
    $datetime[] = $start_datetime->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT, $timezone);

    $end_datetime = new DrupalDateTime($max . '+ 4 hours', new \DateTimeZone($timezone));
    $datetime[] = $end_datetime->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT, $timezone);

    return $datetime;
  }

}
