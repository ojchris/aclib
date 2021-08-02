<?php

namespace Drupal\aclib_refdb;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Url;
use Drupal\Core\Routing\TrustedRedirectResponse;

use Drupal\node\NodeInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;

class AclibRefdbService {

  use StringTranslationTrait;

  const DEFAULT_TIMEZONE = 'America/New_York';
  const REFDB_BUNDLE = 'reference_database';
  const HQ_FIELD = 'field_hq_only';
  const INTERNAL_URL = 'field_internal_url';
  const EXTERNAL_URL = 'field_external_url';
  const SIGN_ON_FIELD = 'field_require_signon'; 

  /**
   * The current request
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack;    
   */
  public $requestStack;

  /**
   * Drupal\Core\Config\ConfigFactoryInterface definition.
   * 
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  public $config;

  /**
   * Drupal\Core\Entity\EntityTypeManager definition
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  public $entityTypeManager;

  /**
   * Drupal\Core\TempStore\PrivateTempStoreFactory definition.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  public $privateTempStore;

  /**
   * Drupal\Core\State\StateInterface definition.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  public $state;

  /**
   * Drupal\Core\Logger\LoggerChannelFactoryInterface definition.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  public $logger;

  /**
   * Drupal\Core\Messenger\MessengerInterface definition.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  public $messenger;

  /**
   * {@inheritdoc}
   */
  public function __construct(RequestStack $request_stack, ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, PrivateTempStoreFactory $private_temp_store, LoggerChannelFactoryInterface $logger, MessengerInterface $messenger) {
    $this->requestStack = $request_stack->getCurrentRequest();
    $this->config = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->privateTempStore = $private_temp_store;
    $this->logger = $logger;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack'),
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('tempstore.private'),
      $container->get('logger.factory'),
      $container->get('messenger')
    );
  }

  /**
   * Create and save instance of our custom content entity for storing access logs
   *
   * @param array $data
   *   Associative array with keys and values matching entity's fields
  */
  public function logAccess(array $data) {
    $refdb_log_entity = $this->entityTypeManager->getStorage('aclib_refdb_logs')->create($data);
    $refdb_log_entity->save();
  }

  /**
   * Determines if card number given matches pattern set in form aclib_refdb_librarycardform
   *
   * @param integer $card_number
   * @param integer $original
   *
   * @return boolean
  */
  public function cardMemberMatch(int $card_number, int $value, string $setting = 'aclib_refdb_card_accept') {
    $match = FALSE;
    $card_pattern = $this->config->get('aclib_refdb.settings')->get($setting);
    $card_prefix = str_replace('*', '', $card_pattern);
    if (!empty($card_pattern) && is_numeric($card_number)) {
      
      if ((mb_strlen($card_number) == mb_strlen($card_pattern)) && (mb_substr($card_number, 0, mb_strlen($card_prefix)) == $card_prefix)) {
        $match = $value;
      }
    }
    return $match;
  }

  /**
   * Returns array depending on if the user is on-site or not.
   *
   * @param string $aclib_ips
   *  IP address range for ACLD branches.
   * @param string $aclib_hqips
   *  IP address range for ACLD headquarters branch.
   * @param string $user_ip
   *  The current user's IP address.
   * @param bool $hq_only
   *  True if reference database is only available from ACLD HQ.
   *
   * @return bool
   */
  public function location(string $aclib_ips, string $aclib_hqips, string $user_ip, bool $hq_only = FALSE) {
    // On-site is true, else false
    if ($hq_only) {
      return $this->checkips($aclib_hqips, $user_ip);
    }
    return $this->checkips($aclib_ips, $user_ip);
  }

  /**
   * Checks to see if the user IP address is allowed to view database.
   *
   * @param string $allowed_ips
   *   Allowed IP addresses for a particular reference database.
   * @param string $user_ip
   *   The current user's IP address.
   *
   * @return bool
   */
  public function checkips(string $allowed_ips, string $user_ip) {
    $ip_lines = preg_split('/\r\n|[\r\n]/', $allowed_ips);
    $match_base ='';
    foreach ($ip_lines as $ip_value) {
      if (!empty($ip_value)) {
        $subnet_pos = strpos($ip_value, "*");
        if ($subnet_pos) {
          $match_base = mb_substr($ip_value, 0, $subnet_pos);
        }
        else {
          $match_base = $ip_value;
        }
      }

      if (mb_substr($user_ip, 0, mb_strlen($match_base)) == trim($match_base)) {
        return TRUE;
      }
    }
    return FALSE;
  }
 
  /**
   * Prepare a current time for standard drupal's date db field format
   *
   * @return string
   *   A formatted date string
   */
  public function prepareDate() {
    // Fetch default timezone from the main Drupal's configuration at "/admin/config/regional/settings"
    $default_timezone = $this->config->get('system.date')->get('timezone');
    $timezone = isset($default_timezone['default']) && !empty($default_timezone['default']) ? $default_timezone['default'] : static::DEFAULT_TIMEZONE;
    // Create Datetime object with configuration timezone, then return formatted date string with time converted to UTC
    $date = new DrupalDateTime('now', $timezone);
    return $date->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT, ['timezone' => 'UTC']);
  }

  /**
   * Custom method for redirections logic
   *
   * @param object $node
   * @param object $config
   * @param array $session

   * @return object
   *   TrustedRedirectResponse redirection
  */
  public function redirection(NodeInterface $node, object $config, array $session) {
  
    // Check to make sure the nid passed in belongs to a node of type REFDB_BUNDLE
    if ($node->bundle() != static::REFDB_BUNDLE) {
      $front_page = Url::fromRoute('<front>')->toString();
      return new TrustedRedirectResponse($front_page);
    }

    // React on too many wrong entries by user
    if (isset($session['acld_refdb_cardtries']) && $session['acld_refdb_cardtries'] > 3) {
      $nid = $config->get('aclib_refdb_failurenodeid');
      $fallback_node = Url::fromRoute('entity.node.canonical', ['node' => $nid]);
      return new TrustedRedirectResponse($fallback_node->toString());
    }

    $user_ip = $this->requestStack->getClientIp(); //'192.168.1.5';

    // Determine if the user is internal or external to the ACLD network
    $hq_only_field = $node->hasField(static::HQ_FIELD) && !empty($node->get(static::HQ_FIELD)->getValue()) ? $node->get(static::HQ_FIELD)->getValue()[0] : [];
    $hq_only_field_value = isset($hq_only_field['value']) && !empty($hq_only_field['value']) ? TRUE : FALSE;
    
    $is_user_on_site = $this->location($config->get('aclib_refdb_internalips'), $config->get('aclib_refdb_hqips'), $user_ip, $hq_only_field_value);
    
    $external_url = $node->hasField(static::EXTERNAL_URL) && !empty($node->get(static::EXTERNAL_URL)->getValue()) ? $node->get(static::EXTERNAL_URL)->getValue()[0] : [];
    $external_url_value = isset($external_url['uri']) && !empty($external_url['uri']) ? Url::fromUri($external_url['uri'], ['absolute' => TRUE]) : NULL;
    
    $internal_url = $node->hasField(static::INTERNAL_URL) && !empty($node->get(static::INTERNAL_URL)->getValue()) ? $node->get(static::INTERNAL_URL)->getValue()[0] : [];
    $internal_url_value = isset($internal_url['uri']) && !empty($internal_url['uri']) ? Url::fromUri($internal_url['uri']) : NULL;

    $sign_on = $node->hasField(static::SIGN_ON_FIELD) && !empty($node->get(static::SIGN_ON_FIELD)->getValue()) ? $node->get(static::SIGN_ON_FIELD)->getValue()[0] : []; 

    $data = [
      'nid' => $node->id(),
      'datetime' => $this->prepareDate(),
      'remote_addr' => $this->requestStack->getClientIp(),
      'pattern_matched' => isset($session['aclib_refdb_pattern']) ? $session['aclib_refdb_pattern'] : NULL,
    ];

    // Send off-site users to the external URL for HQ-only DBs
    if (!$is_user_on_site && $hq_only_field_value && $external_url_value) {
      return new TrustedRedirectResponse($external_url_value->toString());
    }

    // Determine if the DB requires sign on and handle appropriately
    if (isset($sign_on['value']) && !empty($sign_on['value'])) {
      if ($is_user_on_site && $internal_url_value) {
        $data['location'] = 0;
        // Create instance of our custom logging entity 
        $this->logAccess($data);  
        // Return redirect
        return new TrustedRedirectResponse($internal_url_value->toString());
      }
      else {
        if ($external_url_value) {
          // Create instance of our custom logging entity 
          $data['location'] = 1;
          $this->logAccess($data);  
          // Return redirect
          return new TrustedRedirectResponse($external_url_value->toString());
        }
      }
     
    }

    // If the user is on_site, send them on their way
    if ($is_user_on_site && $internal_url_value) {
      $data['location'] = 0;
      // Create instance of our custom logging entity 
      $this->logAccess($data);  
      // Return redirect
      return new TrustedRedirectResponse($internal_url_value->toString());
    }
  
    // See if they have already been verified previously
    // Send them to the external url if they've logged in before and their cookie is still valid, otherwise give them the library card form.
    if (isset($session['cardVerified']) && $session['cardVerified'] > 0 && $external_url_value) {
      // Create instance of our custom logging entity 
      $data['location'] = 1;
      $this->logAccess($data);  
      // Return redirect
      return new TrustedRedirectResponse($external_url_value->toString());
    }
  }
}