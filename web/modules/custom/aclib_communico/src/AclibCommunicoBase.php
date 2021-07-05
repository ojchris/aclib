<?php

/**
 * @file
 * Contains \Drupal\aclib_communico\AclibCommunicoBase.
 */

namespace Drupal\aclib_communico;

use Symfony\Component\DependencyInjection\ContainerInterface;
use GuzzleHttp\Client;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Component\Serialization\Json;

use AclibCommunicoException;

/**
 * Provides connection with Communico API.
 */
class AclibCommunicoBase {
    
  const TOKEN_URI = 'v3/token';
  const EVENTS_URI = 'v3/attend/events?status=published&start=0&';
  

  // Define some base fields for node
  const NODE_TYPE = 'communico_events';
  const NODE_UID = 1;

  // Fields mapping communico_response_field_name => durpal_field_name
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
   * Drupal\Core\Config\ConfigFactoryInterface definition.
   * 
   * @var Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Guzzle\Client instance.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;
  
  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Drupal\Core\State\StateInterface definition.
   *
   * @var \Drupal\Core\State\StateInterface
   */
   protected $state;

  /**
   * Constructor.
  */
  public function __construct(ConfigFactoryInterface $config_factory, Client $http_client, EntityTypeManagerInterface $entity_type_manager, StateInterface $state) {
    $this->configFactory = $config_factory;
    
    $config = $this->configFactory->get('aclib_communico.settings')->getRawData();
    $this->httpClient = new Client($config['guzzle_options']);

    $this->entityTypeManager = $entity_type_manager;
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('http_client'),
      $container->get('entity_type.manager'),
      $container->get('state')
    );
  }

  /**
   * Check if token exists and if it's valid or expired.
   *
   * @return boolean|array
   *   array with token values if valid, FALSE if not.
   */
  protected function validAuthToken() {
    $token = $this->state->get('aclib_communico.token');
    if (is_array($token) && isset($token['access_token']) && !empty($token['access_token']) && isset($token['expires']) && !empty($token['expires'])) {
      $current_time = \Drupal::time()->getCurrentTime();
      return $current_time >= $token['expires'] ? FALSE : $token;
    }
    return FALSE;
  }

  /**
   * Get token
   *
   * @return array
   *   array with retrieved token values
   */
  public function getAuthToken() {

    $config = $this->configFactory->get('aclib_communico.settings');

    if ($token = $this->validAuthToken()) {
      return $token;
    }
    else {
     
      // $auth = base64_encode($config->get('access_key') . ':' . $config->get('secret_key'));
      // $auth_header = 'Basic ' . $auth;
      //$url = $config->get('api_url') . '/' . static::TOKEN_URI;
      $options = [
        'auth' => [$config->get('access_key'), $config->get('secret_key')],
        'form_params' => ['grant_type' => 'client_credentials'],
        'body' => 'grant_type=client_credentials',
        'headers' => [
          'Content-Type' => 'application/x-www-form-urlencoded;charset=UTF-8',
          //'Authorization' => $auth_header, 
        ],
      ];

      try {
        
        // Do our http request via Guzzle
        $response = $this->httpClient->post(static::TOKEN_URI, $options);

        if ($response->getStatusCode() == 200) {
          $response_data = $response->getBody()->getContents();
          if (is_string($response_data) && substr($response_data, 0, 1) == '{') {
            $token =  Json::decode($response_data);
            $token['expires'] = \Drupal::time()->getCurrentTime() + $token['expires_in'];
            $this->state->set('aclib_communico.token', $token);
            return $token;
          } 
        }
        else {
          throw new AclibCommunicoException('Failed to get access token', $response->getStatusCode());
        }
      }

      catch (AclibCommunicoException $e) {
        $message = 'Failed to get access token: ' . $e->getMessage();
        \Drupal::logger('ac_credentials')->error($message);
        throw new AclibCommunicoException($message, $e->getCode(), $e);
      }

    }
  }

  /**
   * Retrieve events from Communico.
   *
   * @return array
   *   An array of retrieved items.
   */
  public function getEvents() {
    if ($token = $this->getAuthToken()) {

      $config = $this->configFactory->get('aclib_communico.settings');
      // $auth = base64_encode($config->get('access_key') . ':' . $config->get('secret_key'));

      $options = [
        'headers' => [
          //'Content-Type' => 'application/json',
          'Accept' => 'application/json',
          'Authorization' => $token['token_type'] . ' ' . $token['access_token'],
        ],
      ];

      try {
        
        // Do our http request via Guzzle
        $response = $this->httpClient->get(static::EVENTS_URI, $options);
        
        if ($response->getStatusCode() == 200) {
          $json = $response->getBody()->getContents();
          if (is_string($json) && substr($json, 0, 1) == '{') {
            $data =  Json::decode($json);
            // Return data in array ready for generated Node 
            return $this->prepareNode($data);
          }
        }
        else {
          \Drupal::logger('ac_events')->error('Failed to fetch events');
           throw new AclibCommunicoException('Failed to fetch events', $response->getStatusCode());
        }
      }
      catch (AclibCommunicoException $e) {
        $message = 'Failed to get events: ' . $e->getMessage();
        \Drupal::logger('ac_events')->error($message);
        throw new AclibCommunicoException($message, $e->getCode(), $e);
      }
    }
  }

  /**
   * Custom method - parse retrieved data to make it ready for node entity create array
   *
   * @return array
   *   An array with values ready for Node::create method
   */
  protected function prepareNode(array $data) {
    if (isset($data['data']) && isset($data['data']['entries']) && !empty($data['data']['entries'])) {
      
      $fields_map = static::FIELDS_MAP;
      $node_data = [];

      foreach ($data['data']['entries'] as $index => $entry) {
        
        // Set some base fields first
        $node_data[$index] = [
          'type' => static::NODE_TYPE,
          'uid' => static::NODE_UID,
        ];
        
        foreach ($entry as $field_key => $field) {
          if (in_array($field_key, array_keys($fields_map))) {
            $drupal_field_name = $fields_map[$field_key];
            if ($drupal_field_name && !empty($field)) {
              if ($drupal_field_name == 'field_start_date' || $drupal_field_name == 'field_end_date') { // A bit of hardcode like parsing for dates that have space between data and time
                if (strpos($field, ' ') !== FALSE) {
                  $field = str_replace(' ', 'T', $field);
                }
              }

              $node_data[$index][$drupal_field_name] = $field;
            }
          }    
        }
      }
      return $node_data;
    }
  }

}