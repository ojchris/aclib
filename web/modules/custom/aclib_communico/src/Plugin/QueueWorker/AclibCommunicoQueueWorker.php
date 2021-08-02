<?php

namespace Drupal\aclib_communico\Plugin\QueueWorker;

use Symfony\Component\DependencyInjection\ContainerInterface;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Component\Utility\Crypt;

use Drupal\node\NodeInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;

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
  const DEFAULT_TIMEZONE = 'America/New_York';

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
    'privateEvent' => 'field_private_event',
    'venueType' => 'field_venue_type',
    'externalVenueName' => 'field_external_location',
    'externalVenueDescription' => 'field_external_description',
    'externalVenueRoom' => 'field_external_room',
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

    // Fetch default timezone from the main Drupal's configuration at "/admin/config/regional/settings"
    $default_timezone = $this->configFactory->get('system.date')->get('timezone');
    $timezone = isset($default_timezone['default']) && !empty($default_timezone['default']) ? $default_timezone['default'] : static::DEFAULT_TIMEZONE;
    
    foreach ($data as $field_key => $field) {
      if (in_array($field_key, array_keys($fields_map))) {
        $drupal_field_name = $fields_map[$field_key];
        if ($drupal_field_name) {
          
          // Dates need a special care, we need to assign our timezone 
          if ($drupal_field_name == 'field_start_date' || $drupal_field_name == 'field_end_date') {
            $field = $this->prepareDates($drupal_field_name, $node_data['type'], $field, $timezone);
          }
          
          // Strip html tags on this field
          if ($drupal_field_name == 'field_external_description') {
            $field = trim(strip_tags($field));
          }

          // Checkbox needs extra handling
          if ($drupal_field_name == 'field_private_event') {
            $field = $field == NULL ? '0' : $field;
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

  /**
   * Operation callback, prepare dates and convert into UTC string to be saved in database
   *   Note that Communico API returns date strin in ET timezone while Drupal saves all in database considering UTC
   *
   * @param string $field_name
   *   Machine name of a datetime field.
   * @param string $bundle
   *   Node type where date field is attached.
   * @param string $field_value
   *   Date string value returned from Communico API.
   * @param string $timezone
   *   Default timezone string
   *
   * @return string
   *   Formatted date string with time converted to UTC
   */
  protected function prepareDates(string $field_name, string $bundle, string $field_value = 'now', string $timezone = '') {
  
    $date_field_storage = $this->entityTypeManager->getStorage('entity_view_display')->load('node.' . $bundle . '.default');
    
    // First check if there is timezone override on Manage display for a field
    // Fallback to default timezone settings in Drupal at "/admin/config/regional/settings"
    if (is_object($date_field_storage) && !empty($date_field_storage->getRenderer($field_name)->getSettings())) {
      $timezone = isset($date_field_storage->getRenderer('field_start_date')->getSettings()['timezone_override']) && !empty($date_field_storage->getRenderer('field_start_date')->getSettings()['timezone_override']) ? $date_field_storage->getRenderer('field_start_date')->getSettings()['timezone_override'] : $timezone;
    }
    if (strpos($field_value, ' ') !== FALSE) { // A bit of hardcode like parsing for dates that have space between date and time
      $field_value = str_replace(' ', 'T', $field_value);
    }
    // Create Datetime object with configuration timezone, then return formatted date string with time converted to UTC
    $date = new DrupalDateTime($field_value, $timezone);
    return $date->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT, ['timezone' => 'UTC']);
  }

  /**
   * Custom static method for matching a range of local Drupal nodes to compare with response from Communico - for unpublishing action
   *  A bunch of checkups based on API request filters set on configuration and field values on node itself
   *  Events types need to match (computed field on node with hashed string)
   *  Start date and End date node fields need to fit in between API request filters startDate and endDate range set on configuration
   *
   * @param object $config
   *   Machine name of a datetime field.
   * @param object $node
   *   An instance of \Drupal\node\NodeInterface
   *
   * @return bool
   *   Based on this value we find mathing node in Drupal (does not exist anymore on Communico), for unpublishing
   */
  public static function matchRequestFilters(object $config, NodeInterface $node) {
  
    $config_start_date = NULL;
    $config_end_date = NULL;
    $start_date = NULL;
    $end_date = NULL;
    $events_types = NULL;
    $config_types = NULL;

    $match = FALSE;

    // Is startDate set as API call parameter on configuration?
    // If yes prepare date objects for matching
    if ($config->get('startDate')) {
      /* @var \Drupal\Core\Datetime\DrupalDateTime */
      $config_start_date = new DrupalDateTime($config->get('startDate'));
      // Does node have a field_start_date field value? 
      if ($node->hasField('field_start_date') && !empty($node->get('field_start_date')->getValue()) && !empty($node->get('field_start_date')->getValue()[0]['value'])) {
        /* @var \Drupal\Core\Datetime\DrupalDateTime */
        $start_date = new DrupalDateTime($node->get('field_start_date')->getValue()[0]['value']);
      } 
    }
  
    // Is endDate set as API call parameter on configuration?
    // If yes prepare date objects for matching
    if ($config->get('endDate')) {
      /* @var \Drupal\Core\Datetime\DrupalDateTime */
      $config_end_date = new DrupalDateTime($config->get('endDate'));
      // Does node have a field_end_date field value? 
      if ($node->hasField('field_end_date') && !empty($node->get('field_end_date')->getValue()) && !empty($node->get('field_end_date')->getValue()[0]['value'])) {
        /* @var \Drupal\Core\Datetime\DrupalDateTime */
        $end_date = new DrupalDateTime($node->get('field_end_date')->getValue()[0]['value']);
      } 
    }
  
    // Are events types set as API call parameter on configuration?
    // If yes prepare hash strings for matching
    if (is_array($config->get('types')) && !empty($config->get('types'))) {
      $config_types = Crypt::hashBase64(implode('__', array_values($config->get('types'))));
      $events_types = !empty($node->get('communico_events_type')->getValue()) && !empty($node->get('communico_events_type')->getValue()[0]['value']) ? $node->get('communico_events_type')->getValue()[0]['value'] : '';
    }
  
    if ($config_types) {
      if ($events_types && $config_types == $events_types) { // Hashed values of Events type set on config matches with those on node ("communico_events_type" computed field)
        $match = 'events'; 
      } 
    }

    if ($config_start_date && $config_end_date) {
      if ($start_date && $end_date) { 
        $match = $start_date >= $config_start_date && $end_date <= $config_end_date ? 'dates' : FALSE;
      }
      else {
        $match = $match == 'events' ? TRUE : FALSE; // We do not have both dates set on node so it does not match with communico filters and - we should not unpublish
      } 
    }
    else {
      $match = $match != 'events' ? TRUE : FALSE; // No filters by start and end date set on configuration for API call - we should unpublish if events filter matches too
    } 

    return $match;
  }
}