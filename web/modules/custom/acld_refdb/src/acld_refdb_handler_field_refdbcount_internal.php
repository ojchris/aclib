<?php
namespace Drupal\acld_refdb;

class acld_refdb_handler_field_refdbcount_internal extends views_handler_field_custom {
  
  function render($values) {
    $result = db_select('refdb_access_log', 'thecount')
      ->fields('thecount')
      ->condition('nid', $values->nid, '=')
      ->condition('location', 0, '=');
    
    $min = strtotime($this->view->filter['timestamp']->value['min']);
    $max = strtotime($this->view->filter['timestamp']->value['max']);
    
    if ($min && $max) {
      $result->condition('timestamp', array($min, $max), 'BETWEEN');
    }
    
    $count = $result->execute()->rowCount();
    return $count;
    
  }

}
