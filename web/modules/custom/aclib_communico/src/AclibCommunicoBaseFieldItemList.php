<?php

namespace Drupal\aclib_communico;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\Component\Utility\Crypt;

use Drupal\aclib_communico\Plugin\QueueWorker\AclibCommunicoQueueWorker;

/**
 * Represents a configurable entity path field.
 */
class AclibCommunicoBaseFieldItemList extends FieldItemList {

  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  protected function computeValue() {

    $entity = $this->getEntity();
    $field_name = $this->getFieldDefinition()->getName();
    $config = \Drupal::config('aclib_communico.settings'); 
    
    switch ($field_name) {
      
      case 'communico_fields_hash':
        $value = $this->computeHash($config);
      break;

      case 'communico_events_type':
       $value = $this->computeEvents($config);
      break;
    }

    $this->list[0] = $this->createItem(0, $value);
  }

  /**
   * Custom (compute) method for "communico_fields_hash" base field defined in acilb_communico.module
   *
   * @param object $config
   *   instance of \Drupal\Core\Config\ImmutableConfig
   *
   * @return string
   *   hashed string generated of imploded communico related fields' values 
   */
  protected function computeHash(object $config) {

    $entity = $this->getEntity();

    $field_values = [
      'type' => $entity->getType(),
      'uid' => $config->get('node_author') ? $config->get('node_author') : 1
    ];

    foreach(array_values(AclibCommunicoQueueWorker::FIELDS_MAP) as $field_name) {
      if ($field_name && !empty($field_name) && $entity->hasField($field_name)) {
        $field_values[$field_name] =  !empty($entity->get($field_name)->getValue()) ? $entity->get($field_name)->getValue()[0]['value'] : '';
      }
    }

    ksort($field_values); // Essential for matching with remote api fields values
    return Crypt::hashBase64(implode('__', array_values($field_values)));

  }

  /**
   * Custom (compute) method for "communico_events_type" base field defined in acilb_communico.module
   *
   * @param object $config
   *   instance of \Drupal\Core\Config\ImmutableConfig
   *
   * @return string
   *   hashed string generated of imploded Event types values set on main configuration
   */
  protected function computeEvents($config) {
    $types = $config->get('types') ? $config->get('types') : [];
    return Crypt::hashBase64(implode('__', array_values($types))); 
  }
}