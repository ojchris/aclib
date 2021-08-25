<?php

namespace Drupal\aclib_migrate\Plugin\migrate\source;

use Drupal\file\Plugin\migrate\source\d7\File;

/**
 * Custom ACLIB Drupal 7 source for files.
 *
 * @MigrateSource(
 *   id = "aclib_files",
 *   source_module = "file"
 * )
 */
class AclibFiles extends File {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = parent::query();

    // Remove the orderby from the parent.
    unset($query->getOrderBy()['f.timestamp']);

    if (isset($this->configuration['aclib_file_identifier'])) {
      switch ($this->configuration['aclib_file_identifier']) {
        case 'blog_images':
          $query->condition('type', 'image');
          // Last 100
          //$query->range(0, 100);
          // Last 1000.
          //$query->range(0, 1000);
          // Past year.
          //$query->condition('timestamp', (time() - 60*60*24*365), '>');
          // Only migrate files created since 1/1/2020.
          $query->condition('timestamp', 1577833200, '>=');
          $query->orderBy('fid', 'DESC');
          break;
      }
    }
    return $query;
  }
}
