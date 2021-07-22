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

    if (isset($this->configuration['aclib_file_identifier'])) {
      switch ($this->configuration['aclib_file_identifier']) {
        case 'test_latest_images':
          $query->condition('type', 'image');
          $query->orderBy('fid', 'DESC');
          $query->range(0, 10);
          break;
        case 'blog_images':
          $query->condition('type', 'image');
          // Past week.
          $query->condition('timestamp', (time() - 60*60*24*7), '>');
          // Past month.
          //$query->condition('timestamp', (time() - 60*60*24*31), '>');
          // Past year.
          //$query->condition('timestamp', (time() - 60*60*24*365), '>');
          $query->orderBy('fid', 'ASC');
          break;
      }
    }
    return $query;
  }

}
