<?php

namespace Drupal\aclib_migrate\Plugin\migrate\source;

use Drupal\media_migration\Plugin\migrate\source\d7\FileEntityItem;
use Drupal\migrate\Row;

/**
 * Custom ACLIB Drupal 7 source for file entities.
 *
 * @MigrateSource(
 *   id = "aclib_file_entity",
 *   source_module = "file_entity"
 * )
 */
class AclibFileEntity extends FileEntityItem {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = parent::query();

    // Remove the orderby from the parent.
    unset($query->getOrderBy()['fm.timestamp']);

    if (isset($this->configuration['aclib_file_identifier'])) {
      switch ($this->configuration['aclib_file_identifier']) {
        case 'subset':
          // Last 100
          //$query->range(0, 100);
          // Last 1000.
          //$query->range(0, 1000);
          // Past year.
          //$query->condition('timestamp', (time() - 60*60*24*365), '>');
          // Only migrate files entities created since 1/1/2020.
          $query->condition('timestamp', 1577833200, '>=');
          $query->orderBy('fid', 'DESC');
          break;
      }
    }
    return $query;
  }

}
