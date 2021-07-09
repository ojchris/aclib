<?php

namespace Drupal\aclib_communico\Plugin\QueueWorker;

use Symfony\Component\DependencyInjection\ContainerInterface;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;

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
   * Drupal\Core\Logger\LoggerChannelFactoryInterface definition.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $logger;
  
  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entityTypeManager, LoggerChannelFactoryInterface $logger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entityTypeManager;
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
        catch (\Exception $e) {
          $error = $this->t('Updating Communico event nodes failed on cron run for event: @title with message: @message', ['@title' => $data['title'], '@message' => $e->getMessage()]);
          $this->logger->get('aclib_communico')->error($error);
        }
      break;

      case 'create':

        try {
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