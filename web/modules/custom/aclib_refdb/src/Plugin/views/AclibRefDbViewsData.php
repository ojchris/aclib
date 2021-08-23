<?php

namespace Drupal\aclib_refdb\Plugin\views;

use Drupal\views\EntityViewsData;

/**
 * Provides the views data for Aclib RefDb logs entity.
 */
class AclibRefDbViewsData extends EntityViewsData {

  /**
   * {@inheritdoc}
   */
  public function getViewsData() {

    // Invoke parent.
    $data = parent::getViewsData();

    // Create our group wothin views.
    $data['aclib_refdb_logs']['table']['group'] = t('ACLIB');

    // Essential for relationships since on our entity "id" is a primary key.
    $data['aclib_refdb_logs']['table']['base']['field'] = 'nid';

    // Set relationship with node_field_data table.
    $data['aclib_refdb_logs']['nid']['relationship'] = [
      'title' => t('ACLIB RefDb logs'),
      'help' => t('ACLIB RefDb logs table.'),
      'base' => 'node_field_data',
      'base field' => 'nid',
      'field' => 'nid',
      'id' => 'standard',
    ];

    // Add computed field in the aclib_refdb_logs entity's table group.
    $data['aclib_refdb_logs']['computed'] = [
      'title' => t('Count access records'),
      'help' => t('A sum of records for various Ref DB fields.'),
      'field_alias' => 'aclib_refdb_computed_field',
      'field' => [
        // ID of the field handler to use.
        /** @var \Drupal\aclib_refdb\Plugin\views\field\AclibRefDbComputedField */
        'id' => 'aclib_refdb_computed_field',
        'click sortable' => TRUE,
        'relationship' => [
          'title' => t('ACLIB RefDb logs'),
          'help' => t('ACLIB RefDb logs table.'),
          'base' => 'node_field_data',
          'base field' => 'nid',
          'id' => 'standard',
        ],
      ],
    ];

    // A few essentials for a date filter.
    $data['aclib_refdb_logs']['datetime']['filter']['id'] = 'aclib_refdb_datetime';
    $data['aclib_refdb_logs']['datetime']['filter']['field_name'] = 'datetime';
    return $data;
  }

}
