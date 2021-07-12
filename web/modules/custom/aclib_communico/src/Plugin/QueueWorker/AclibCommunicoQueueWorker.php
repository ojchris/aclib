<?php

namespace Drupal\aclib_communico\Plugin\QueueWorker;

use Symfony\Component\DependencyInjection\ContainerInterface;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Component\Utility\Crypt;

use Drupal\node\NodeInterface;

/**
 * Creates or updates nodes based on results retrived by API call/response.
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
  const HASH_FIELD = 'communico_fields_hash';
  const EVENTS_TYPE_FIELD = 'communico_events_type';

  /**
   * Fields mapping
   * 
   * communico_response_field_name => drupal_field_name
  */
  const FIELDS_MAP = [
    // Communico fields that we use so far
    'eventId' => 'field_communico_event_id',
    'title' => 'title',
    'eventStart' => 'field_start_date',
    'eventEnd' => 'field_end_date',
    'locationName' => 'field_location',
    'roomName' => 'field_room',
    'modified' => 'field_communico_modified',
    // Other available fields that we do not use yet
    'published' => NULL,
    'roomId' => NULL,
    'locationId' => NULL,
    'recurringId' => NULL,
    'newEventId' => NULL,
    'subTitle	' => NULL,
    'shortDescription' => NULL,
    'description' => NULL,
  ];

  /**
   * Drupal\Core\Config\ConfigFactoryInterface definition.
   * 
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

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
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, LoggerChannelFactoryInterface $logger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
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
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('logger.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
  
    // Load our configuration object
    $config = $this->configFactory->get('aclib_communico.settings');
  
    // Prepare our data for to be associative array ready for node save
    $node_data = $this->prepareItem($data, $config);

    // Find possible existing equivalent communico event in our (Drupal) database 
    $properties = [
      'field_communico_event_id' => $node_data['field_communico_event_id']
    ];
  
    $communico_event_load = $this->entityTypeManager->getStorage('node')->loadByProperties($properties);
    $communico_event = is_array($communico_event_load) && !empty($communico_event_load) ? reset($communico_event_load) : NULL;

    if ($communico_event instanceof NodeInterface) { // This is existing node in our Drupal that matches event on communico via eventId
      if ($config->get('update')) { // Make sure this optin is checked on configuration
        $this->saveItem($node_data, 'update', $communico_event);
      }
    }
    // Yet this is a communico event that we did not import so far
    else {
      $this->saveItem($node_data, 'create'); 
    }
  }

  /**
   * Custom method - parse retrieved data to make it ready for node entity create array
   *
   * @param array $data
   *   Associative array with values retrieved from Communico API.
   * @param object $config
   *   instance of \Drupal\Core\Config\ImmutableConfig
   *
   * @return array
   *   An array with values ready for Node::create method
   */
  protected function prepareItem(array $data, object $config) {

    $fields_map = static::FIELDS_MAP;
    $node_data = [
      'type' => $config->get('node_type') ? $config->get('node_type') : static::NODE_TYPE,
      'uid' => $config->get('node_author') ? $config->get('node_author') : static::NODE_UID
    ];

    foreach ($data as $field_key => $field) {
      if (in_array($field_key, array_keys($fields_map))) {
        $drupal_field_name = $fields_map[$field_key];
        if ($drupal_field_name) {
          if ($drupal_field_name == 'field_start_date' || $drupal_field_name == 'field_end_date') { // A bit of hardcode like parsing for dates that have space between date and time
            if (strpos($field, ' ') !== FALSE) {
              $field = str_replace(' ', 'T', $field);
            }
          }
          $node_data[$drupal_field_name] = $field;
        }
      }    
    }

    ksort($node_data); // Essential for matching with local/drupal fields values
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

          $remote_values_hash = Crypt::hashBase64(implode('__', array_values($data)));
          $drupal_values_hash = '';
          if ($communico_event->hasField(static::HASH_FIELD)) {
            $drupal_values_hash = !empty($communico_event->get(static::HASH_FIELD)->getValue()) && isset($communico_event->get(static::HASH_FIELD)->getValue()[0]['value']) ?  $communico_event->get(static::HASH_FIELD)->getValue()[0]['value'] : '';
          }
          else {
            $warning = $this->t('Communico hash field not existing.');
            $this->logger->get('aclib_communico')->warning($warning);
          }
          
          if ($remote_values_hash != $drupal_values_hash) {
            foreach ($data as $field_name => $field_value) {
              if ($field_name !== 'type' && $field_name !== 'uid') {
                $value[0]['value'] = $field_value;
                $communico_event->set($field_name, $value);
              }
            }
     
            // Finally save updates on our node object
            if ($communico_event->save()) {
              $status = $this->t('Existing node updated with a new version imported via Communico API: @title ', ['@title' => $data['title']]);
              $this->logger->get('aclib_communico')->notice($status);
            }
          }
          
        }
        catch (\Exception $e) {
          $error = $this->t('Updating Communico event nodes failed on cron run for event: @title with message: @message', ['@title' => $data['title'], '@message' => $e->getMessage()]);
          $this->logger->get('aclib_communico')->error($error);
        }
      break;

      case 'create':

        try {
       
          $node = $this->entityTypeManager->getStorage('node')->create($data);
          if ($node->save()) {
            $status = $this->t('A new node imported via Communico API; @title', ['@title' => $data['title']]);
            $this->logger->get('aclib_communico')->notice($status);
          }
        }
        catch (\Exception $e) {
          $error = $this->t('Creating Communico event nodes failed on cron run for event: @title with message: @message', ['@title' => $data['title'], '@message' => $e->getMessage()]);
          $this->logger->get('aclib_communico')->error($error);
        }
      break;
     
    }
  } 
}