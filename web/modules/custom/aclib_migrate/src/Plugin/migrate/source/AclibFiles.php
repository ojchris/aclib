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

    if (isset($this->configuration['aclib_file_identifier'])) {
      switch ($this->configuration['aclib_file_identifier']) {
        case 'test_latest_images':
          $query->condition('type', 'image');
          $query->orderBy('fid', 'DESC');
          $query->
          $query->range(0, 10);
          break;
        case 'blog_images':
          $query->condition('type', 'image');
          // Last 100
          $query->range(0, 100);
          // Last 1000.
          //$query->range(0, 1000);
          // Past year.
          //$query->condition('timestamp', (time() - 60*60*24*365), '>');
          $query->orderBy('fid', 'DESC');
          break;
      }
    }
    return $query;
  }
}
