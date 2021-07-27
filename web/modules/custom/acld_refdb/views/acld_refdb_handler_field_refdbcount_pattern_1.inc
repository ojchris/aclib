<?php

/**
 * $Id$
 */
class acld_refdb_handler_field_refdbcount_pattern_1 extends views_handler_field_custom {

  /**
   *
   */
  public function render($values) {

    $or = db_or();
    $or->isNull('pattern_matched');
    $or->condition('pattern_matched', 1, '=');

    $result = db_select('refdb_access_log', 'thecount')
      ->fields('thecount')
      ->condition('nid', $values->nid, '=')
      ->condition($or);

    $min = strtotime($this->view->filter['timestamp']->value['min']);
    $max = strtotime($this->view->filter['timestamp']->value['max']);

    if ($min && $max) {
      $result->condition('timestamp', [$min, $max], 'BETWEEN');
    }

    $count = $result->execute()->rowCount();
    return $count;
  }

}
