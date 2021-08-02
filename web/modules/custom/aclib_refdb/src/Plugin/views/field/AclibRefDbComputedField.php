<?php
 
/**
 * @file
 * Definition of Views plugin field - Drupal\aclib_refdb\Plugin\views\field\AclibRefDbComputedField
 */
 
namespace Drupal\aclib_refdb\Plugin\views\field;
 
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
 
/**
 * Field handler for properties calculations
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("aclib_refdb_computed_field")
 */
class AclibRefDbComputedField extends FieldPluginBase {
 
  const PROPERTIES = [
    'location'  => [
      'internal' => [
        'key' => 'internal',
        'value' => '0',
        'label' => 'Internal access count',
      ],
      'external' => [
        'key' => 'external',
        'value' => '1',
        'label' => 'External access count',
      ],
      'overall' => [
        'key' => 'overall',
        'value' => 'all',
        'label' => 'Overall access count',
      ],
    ],
    'pattern_matched' => [
      'default' => [
        'key' => 'pattern',
        'label' => 'Pattern count',
        'value' => '1',
      ],
      'alternate' => [
        'key' => 'pattern_alternate',
        'value' => '2',
        'label' => 'Pattern alternate count',
      ],
    ],
  ];
  
  /**
   * @{inheritdoc}
   *
   * If we turn on views query debugging we should see these clauses applied.
   */
  public function query() {
    // Leave empty to avoid a query on this field.
  }

  /**
   * @{inheritdoc}
   *
   * Define the available options
   * @return array
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['property'] = ['default' => 'internal'];
    return $options;
  }
 
  /**
   * Provide the options form.
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {

    // Prepare options for property type dropdown
    $properties = [];
    foreach (static::PROPERTIES as $base_field => $property) {
      foreach ($property as $property_value => $property_data) {
        $properties[$property_value] = $this->t($property_data['label']);
      }
    }

    $form['property'] = [
      '#title' => $this->t('Choose property'),
      '#type' => 'select',
      '#default_value' => $this->options['property'],
      '#options' => $properties,
    ];
    parent::buildOptionsForm($form, $form_state);
  }
 
  /**
   * @{inheritdoc}
   */
  public function render(ResultRow $values) {
      
    $aclib_refdb_storage = \Drupal::service('entity_type.manager')->getStorage('aclib_refdb_logs');
    foreach (static::PROPERTIES as $base_field => $property) {
      foreach ($property as $property_value => $property_data) {
        if ($this->options['property'] == $property_value) {
          switch($base_field) {
  
          case 'location':
              $aclib_refdb_storage_count = is_numeric($property_data['value']) ? $aclib_refdb_storage->getQuery()->condition($base_field, $property_data['value'])->count() : $aclib_refdb_storage->getQuery()->count();
              return $aclib_refdb_storage_count->execute();
            break;

            case 'pattern_matched':
              $aclib_refdb_storage_count = $aclib_refdb_storage->getQuery()->condition($base_field, $property_data['value'])->count()->execute();
              return $aclib_refdb_storage_count; 
            break;
          }
        }
      }
    }
  }
}