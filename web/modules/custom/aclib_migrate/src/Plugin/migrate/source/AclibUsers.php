<?php

namespace Drupal\aclib_migrate\Plugin\migrate\source;

use Drupal\user\Plugin\migrate\source\d7\User;

/**
 * Custom ACLIB Drupal 7 source for users.
 *
 * @MigrateSource(
 *   id = "aclib_users",
 *   source_module = "user"
 * )
 */
class AclibUsers extends User {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = parent::query();

    // Do not migrate UID 1.
    $query->condition('u.uid', 1, '>');
    return $query;
  }

}
