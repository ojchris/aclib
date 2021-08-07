<?php

namespace Drupal\aclib_migrate\Plugin\migrate\source;

use Drupal\node\Plugin\migrate\source\d7\Node;
use Drupal\migrate\Row;

/**
 * Custom ACLIB Drupal 7 node source from database.
 *
 * Includes URL aliases, limits nodes by date.
 *
 * Available configuration keys:
 * - node_type: The node_types to get from the source - can be a string or
 *   an array. If not declared then nodes of all types will be retrieved.
 *
 * @MigrateSource(
 *   id = "aclib_d7_node",
 *   source_module = "node"
 * )
 */
class AclibNodes extends Node {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = parent::query();

    if (isset($this->configuration['node_type'])) {
      if (in_array($this->configuration['node_type'], ['pressrelease', 'blog_entry'])) {
        // Only migrate nodes created since 1/1/2020.
        $query->condition('n.created', 1577833200, '>=');
      }
      if (in_array($this->configuration['node_type'], ['referencedb'])) {
        // Only migrate published nodes - see OA5740).
        $query->condition('n.status', 1);
      }
    }

    // Order by NID descending for easier testing.
    $query->orderBy('n.nid', 'DESC');

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    // Include path alias.
    $nid = $row->getSourceProperty('nid');
    $query = $this->select('url_alias', 'ua')
      ->fields('ua', ['alias']);
    $query->condition('ua.source', 'node/' . $nid);
    $alias = $query->execute()->fetchField();
    if (!empty($alias)) {
      $row->setSourceProperty('alias', '/' . $alias);
    }

    return parent::prepareRow($row);
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return ['alias' => $this->t('Path alias')] + parent::fields();
  }

}
