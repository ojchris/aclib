<?php

namespace Drupal\aclib_refdb\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;

use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ViewExecutable;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\ResultRow;

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

    foreach ($count_data as $base_field => $options) {
      foreach ($options as $option_key => $option_label) {
        if ($this->options['property'] == $option_key) {
          $value = $option_key == 'overall' ? NULL : $option_key;
          $query_count = $this->aclibService->defaultCountQuery($base_field, $nid, $value);
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

      // Set first logic for Pattern matched type of fields, a special case.
      if (strpos($this->options['property'], '*') !== FALSE) {
        $patterns = $this->aclibService->getPatterns();
        if (!empty($patterns)) {
          foreach ($patterns as $pattern) {
            if ($pattern == $this->options['property']) {
              $formula = $this->aclibService->queryCountProperty('pattern_matched', $pattern);
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
            $value = $this->options['property'] == 'overall' ? NULL : $this->options['property'];
            $formula = $this->aclibService->queryCountProperty($property_key, $value);
            $this->query->addOrderBy(NULL, $formula, $order, $this->options['property'], $params);
          }
        }
      }
      // $this->query->addTag('debug');
    }
  }

}
