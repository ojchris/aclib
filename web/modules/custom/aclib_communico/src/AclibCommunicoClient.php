<?php

/**
 * @file
 * Contains \Drupal\aclib_communico\AclibCommunicoBase.
 */

namespace Drupal\aclib_communico;

use Symfony\Component\DependencyInjection\ContainerInterface;
use GuzzleHttp\Client;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Component\Serialization\Json;

/**
 * Provides connection with Communico API.
 */
class AclibCommunicoClient {
    
  /**
   * Drupal\Core\StringTranslation\StringTranslationTrait definition.
   * Wrapper methods for \Drupal\Core\StringTranslation\TranslationInterface.
   * 
   * @var \Drupal\Core\StringTranslation\StringTranslationTrait
   */
  use StringTranslationTrait;

  /**
   * Drupal\Core\Config\ConfigFactoryInterface definition.
   * 
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Guzzle\Client instance.
   *
   * @var \GuzzleHttp\Client
   */
  public $httpClient;
  
  /**
   * Drupal\Core\State\StateInterface definition.
   *
   * @var \Drupal\Core\State\StateInterface
   */
   protected $state;

  /**
   * Drupal\Core\Logger\LoggerChannelFactoryInterface definition.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $logger;

  /**
   * Drupal\Core\Messenger\MessengerInterface definition.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructor for AclibCommunicoClient object
  */
  public function __construct(ConfigFactoryInterface $config_factory, Client $http_client, StateInterface $state, LoggerChannelFactoryInterface $logger, MessengerInterface $messenger) {

    $this->configFactory = $config_factory;
    $config = $this->configFactory->get('aclib_communico.settings')->getRawData();
    $this->httpClient = new Client($config['guzzle_options']);
    $this->state = $state;
    $this->logger = $logger;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('http_client'),
      $container->get('state'),
      $container->get('logger.factory'),
      $container->get('messenger')
    );
  }

  /**
   * Get token
   *
   * @return array
   *   array with retrieved token values
   */
  public function getAuthToken() {

    $config = $this->configFactory->get('aclib_communico.settings');
    $status = $this->t('Access token valid, connection with API successful.');

    if ($token = $this->validAuthToken()) {
      $this->logger->get('aclib_communico')->notice($status);
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
        $response = $this->httpClient->post($config->get('token_endpoint'), $options);

        if ($response->getStatusCode() == 200) {
          $response_data = $response->getBody()->getContents();
          if (is_string($response_data) && substr($response_data, 0, 1) == '{') {
            $token =  Json::decode($response_data);
            $token['expires'] = \Drupal::time()->getCurrentTime() + $token['expires_in'];
            $this->state->set('aclib_communico.token', $token);
            $this->logger->get('aclib_communico')->notice($status);
            return $token;
          } 
        }
        else {
          $this->logger->get('aclib_communico')->error($this->t('Failed to get access token'));
        }
      }

      catch (\Exception $e) {
        $message = $this->t('Failed to get access token: @message', ['@message' => $e->getMessage()]);
        $this->logger->get('aclib_communico')->error($message);
        throw new \Exception($message);
      }
    }
  }

  /**
   * Generic method for various GET requests to Communico.
   *
   * @param string $uri
   *   API endpoint URL segment.
   * @param array $options
   *   Associative array of request parameters, override or extend.
   * 
   * @return array
   *   An array of retrieved items.
   */
  public function get(string $uri, array $options = []) {
  
    if ($token = $this->getAuthToken()) {
      
      // So far these are our default options
      $default_options = [
        'headers' => [
          'Accept' => 'application/json',
          'Authorization' => $token['token_type'] . ' ' . $token['access_token'],
        ],
        'query' => [
          'status' => 'published',
           // It is weird that Communico API does not have unlimited number of entries, i.e. value 0 or -1 or such.
           // If option is avoided here it still returns default 10. Therefore we set some random big number assuming the real settings comes from configuration
           'limit' => 100
         ]
      ];

      // Default options can be overriden or extended with provided $options function param
      $options = !empty($options) ? array_merge($default_options, $options) : $default_options;
      
      try {
        
        // Do our http request via Guzzle
        $response = $this->httpClient->get($uri, $options);
        
        if ($response->getStatusCode() == 200) {
          $json = $response->getBody()->getContents();
          if (is_string($json) && substr($json, 0, 1) == '{') {
            // Return data in array ready for generated Node 
            return Json::decode($json);
          }
          else {
            return $json;
          }
        }
      }
      catch (\Exception $e) {
        throw new \Exception($e->getMessage());
      }

    }
  }

  /**
   * Private method - check if token exists and if it's valid or expired.
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
}