<?php

namespace Drupal\aclib_communico\Plugin\QueueWorker;

use Symfony\Component\DependencyInjection\ContainerInterface;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Component\Utility\Crypt;

use Drupal\node\NodeInterface;

use Drupal\aclib_communico\AclibCommunicoClient;

/**
 * Fetches data from Communico and creates appropriate nodes.
 *
 * @QueueWorker(
 *   id = "aclib_communico_queue",
 *   title = @Translation("Aclib Communico Queue Worker"),
 *   cron = {"time" = 10}
 * )
 */
class AclibCommunicoQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * Drupal\Core\StringTranslation\StringTranslationTrait definition.
   * Wrapper methods for \Drupal\Core\StringTranslation\TranslationInterface.
   * 
   * @var \Drupal\Core\StringTranslation\StringTranslationTrait
   */
  use StringTranslationTrait;

  // Define some base fields for node
  const NODE_TYPE = 'communico_events';
  const NODE_UID = 1;
  const HASH_FIELD = 'field_fields_hash';
  /**
   * Fields mapping
   * 
   * communico_response_field_name => drupal_field_name
  */
  const FIELDS_MAP = [
    
    'title' => 'title',
    'eventId' => 'field_communico_event_id',
    'eventStart' => 'field_start_date',
    'eventEnd' => 'field_end_date',
    'locationName' => 'field_location',
    'roomName' => 'field_room',
    
    // Other available fields that we do not use yet
    'published' => NULL,
    'roomId' => NULL,
    'locationId' => NULL,
    'recurringId' => NULL,
    'modified' => NULL,
    'newEventId' => NULL,
    'subTitle	' => NULL,
    'shortDescription' => NULL,
    'description' => NULL,
  ];


  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;
  
  /**
   * Drupal\Core\Entity\EntityFieldManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Drupal\Core\Logger\LoggerChannelFactoryInterface definition.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $logger;
  
  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager, LoggerChannelFactoryInterface $logger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->logger = $logger;
  }
  
  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('logger.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
  
    $node_data = $this->prepareItem($data);

    // Find possible existing equilent communico event in our (Drupal) database 
    $communico_event_load = $this->entityTypeManager->getStorage('node')->loadByProperties(['field_communico_event_id' => $node_data['field_communico_event_id']]);
    $communico_event = is_array($communico_event_load) && !empty($communico_event_load) ? reset($communico_event_load) : NULL;

    if ($communico_event instanceof NodeInterface) { // This is existing node in our Drupal that matches event on communico via eventId
      $this->saveItem($node_data, 'update', $communico_event);
    }
    // Yet this is a communico event that we did not import yet
    else {
      $this->saveItem($node_data, 'create'); 
    }
  }

  /**
   * Custom method - parse retrieved data to make it ready for node entity create array
   *
   * @return array
   *   An array with values ready for Node::create method
   */
  public function prepareItem(array $data) {

    $fields_map = static::FIELDS_MAP;

    // Set some base fields first
    $node_data = [
      'type' => static::NODE_TYPE,
      'uid' => static::NODE_UID,
    ];

    foreach ($data as $field_key => $field) {
      if (in_array($field_key, array_keys($fields_map))) {
        $drupal_field_name = $fields_map[$field_key];
        if ($drupal_field_name && !empty($field)) {
          if ($drupal_field_name == 'field_start_date' || $drupal_field_name == 'field_end_date') { // A bit of hardcode like parsing for dates that have space between date and time
            if (strpos($field, ' ') !== FALSE) {
              $field = str_replace(' ', 'T', $field);
            }
          }
          $node_data[$drupal_field_name] = $field;
        }
      }    
    }
    return $node_data;
  }

  /**
   * Operation callback, either create or update or unpublish nodes.
   *
   * @param array $data
   *   Associative array with node field names and values to be created/updated.
   * @param string $op
   *   Operation type.
   * @param object $communico_event
   *   Node entity object.
   */
  protected function saveItem(array $data, string $op, object $communico_event = NULL) {

    switch ($op) {
    
      case 'update':
        
        try {
          
          
          $fields = $this->entityFieldManager->getFieldDefinitions('node', static::NODE_TYPE);
          $hash_fields_name = [];
          $fields_values = [
            'title' => $communico_event->getTitle()
          ];
          foreach ($fields as $field) {
            if ($field->getType() == 'aclib_communico_fields_hash') {
              $hash_fields_name[$field->getName()] = $field->getName();
            }
            else {
              if (in_array($field->getName(), array_values(static::FIELDS_MAP)) && strpos($field->getName(), 'field_') !== FALSE) { //$field->getName() !== 'type' && $field->getName() !== 'uid') {
                $drupal_field_value = !empty($communico_event->get($field->getName())->getValue()) && isset($communico_event->get($field->getName())->getValue()[0]['value']) ?  $communico_event->get($field->getName())->getValue()[0]['value'] : '';
                if ($field->getName() == 'field_start_date' || $field->getName() == 'field_end_date') { // A bit of hardcode like parsing for dates that have space between date and time
/*
                  if (strpos($drupal_field_value , 'T') !== FALSE) {
                    $drupal_field_value = str_replace('T', ' ', $drupal_field_value);
                  }
*/
                }
                if ($field->getName() != 'field_start_date' || $field->getName() != 'field_end_date') {
                  $fields_values[$field->getName()] = $drupal_field_value;
                }
              }
            }
          }

          if (!empty($hash_fields_name)) {

            //unset($data['field_start_date']);
            //unset($data['field_end_date']);
            unset($data['uid']);
            unset($data['type']); 

            $remote_values_hash = Crypt::hashBase64(implode('__', array_values($data)));
            $drupal_values_hash = Crypt::hashBase64(implode('__', array_values($fields_values)));
            $hash_field = reset($hash_fields_name);
            
            $this->logger->get('aclib_communico_fields')->notice('<pre>' . print_r($data, 1)  . '</pre>');
            $this->logger->get('aclib_communico_fields')->notice('<pre>' . print_r($fields_values, 1)  . '</pre>');

            $this->logger->get('aclib_communico_fields')->notice('<pre>' . print_r($remote_values_hash, 1)  . '</pre>');
            $this->logger->get('aclib_communico_fields')->notice('<pre>' . print_r($drupal_values_hash, 1)  . '</pre>');
            

            if ($remote_values_hash != $drupal_values_hash) {

              $communico_event->set($hash_field, Crypt::hashBase64($remote_values_hash));

              foreach ($data as $field_name => $field_value) {
                if ($field_name !== 'type' && $field_name !== 'uid') {
                  $value[0]['value'] = $field_value;
                  $communico_event->set($field_name, $value);
                }
              }
              if ($communico_event->save()) {
                $status = $this->t('Existing node updated with a new version imported via Communico API: @title ', ['@title' => $data['title']]);
                $this->logger->get('aclib_communico')->notice($status);
              }
            }
          }
          else {
            $error = $this->t('Communico hash field not existing.');
            $this->logger->get('aclib_communico')->error($error);
          }
        }
        catch (\Exception $e) {
          $error = $this->t('Updating Communico event nodes failed on cron run for event: @title with message: @message', ['@title' => $data['title'], '@message' => $e->getMessage()]);
          $this->logger->get('aclib_communico')->error($error);
        }
      break;

      case 'create':

        try {
          
          $fields = $this->entityFieldManager->getFieldDefinitions('node', static::NODE_TYPE);
          //$this->logger->get('aclib_communico_fields')->notice('<pre>' . print_r($fields, 1)  . '</pre>');


          $hash = implode('__', array_values($data));
          $data['field_fields_hash'] = Crypt::hashBase64($hash);
  
          $this->logger->get('aclib_communico_hash')->notice('<pre>' . print_r($data['field_fields_hash'], 1) . '</pre>');

          if ($this->entityTypeManager->getStorage('node')->create($data)->save()) {
            $status = $this->t('A new node imported via Communico API; @title', ['@title' => $data['title']]);
            $this->logger->get('aclib_communico')->notice($status);
          }
        }
        catch (\Exception $e) {
          $error = $this->t('Creating Communico event nodes failed on cron run for event: @title with message: @message', ['@title' => $data['title'], '@message' => $e->getMessage()]);
          $this->logger->get('aclib_communico')->error($error);
        }
      break;

      case 'unpublish':
        try {

          $communico_event->set('status', 0);
          if ($communico_event->save()) {
            $status = $this->t('Existing node unpublished: @title ', ['@title' => $data['title']]);
            $this->logger->get('aclib_communico')->notice($status);
          }
        }
        catch (\Exception $e) {
          $error = $this->t('Unpublishing Communico event nodes failed on cron run: @message', ['@message' => $e->getMessage()]);
          $this->logger->get('aclib_communico')->error($error);
        }
      break;
     
    }
  } 
}