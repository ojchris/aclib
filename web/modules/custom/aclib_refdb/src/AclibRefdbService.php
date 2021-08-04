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

/**
 * A definition of AclibRefdbService class.
 */
class AclibRefdbService {

  use StringTranslationTrait;

  const DEFAULT_TIMEZONE = 'America/New_York';
  const REFDB_BUNDLE = 'reference_database';
  const HQ_FIELD = 'field_hq_only';
  const INTERNAL_URL = 'field_internal_url';
  const EXTERNAL_URL = 'field_external_url';
  const SIGN_ON_FIELD = 'field_require_signon';

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  public $requestStack;

  /**
   * Drupal\Core\Config\ConfigFactoryInterface definition.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  public $config;

  /**
   * Drupal\Core\Entity\EntityTypeManager definition.
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
   * Create and save custom content entity for storing access logs.
   *
   * @param array $data
   *   Associative array with keys and values matching entity's fields.
   */
  public function logAccess(array $data) {
    $refdb_log_entity = $this->entityTypeManager->getStorage('aclib_refdb_logs')->create($data);
    $refdb_log_entity->save();
  }

  /**
   * Compares card number or user's ip address with allowed pattern.
   *
   * @param string $config_pattern_value
   *   Any multiline value set in textareas on configuration page.
   * @param string|int $match_value
   *   The "active" value to match with $config_pattern_value.
   * @param bool $match_length
   *   Whether to involve string lenghts in matching logic as well.
   *
   * @return bool
   *   Whether the matching was found.
   */
  public function multilineMatch(string $config_pattern_value, $match_value, bool $match_length = FALSE) {
    $patterns = preg_split('/\r\n|[\r\n]/', $config_pattern_value);
    $match = '';

    foreach ($patterns as $pattern) {
      if (!empty($pattern)) {
        $sub_pos = strpos($pattern, "*");
        if ($sub_pos) {
          $match = mb_substr($pattern, 0, $sub_pos);
        }
        else {
          $match = $pattern;
        }
      }

      if (mb_substr($match_value, 0, mb_strlen($match)) == trim($match)) {
        // If we have additional matching per lenght of input
        // (user input for card number can be longer).
        if ($match_length) {
          return mb_strlen($pattern) == mb_strlen($match_value) ? $pattern : FALSE;
        }
        else {
          return $pattern;
        }
      }
    }
    return FALSE;
  }

  /**
   * Prepare a current time for standard drupal's date db field format.
   *
   * @return string
   *   A formatted date string
   */
  public function prepareDate() {
    // Fetch default timezone from the main Drupal's configuration
    // at "/admin/config/regional/settings".
    $default_timezone = $this->config->get('system.date')->get('timezone');
    $timezone = isset($default_timezone['default']) && !empty($default_timezone['default']) ? $default_timezone['default'] : static::DEFAULT_TIMEZONE;
    // Create Datetime object with configuration timezone,
    // then return formatted date string with time converted to UTC.
    $date = new DrupalDateTime('now', $timezone);
    return $date->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT, ['timezone' => 'UTC']);
  }

  /**
   * Delete user's session to start over.
   */
  public function sessionDelete() {
    $session = $this->privateTempStore->get('aclib_refdb');
    $session->delete('aclib_refdb');
  }

  /**
   * Custom method for redirections logic.
   *
   * @param \Drupal\node\NodeInterface $node
   *   An instance of Node entity, Reference database node type in question.
   * @param object $config
   *   A config object containing our specific settings.
   * @param array $session
   *   Associative array with session values:
   *   - cardVerified
   *   - aclib_refdb_cardtries
   *   - aclib_refdb_pattern.
   *
   * @return object
   *   TrustedRedirectResponse redirection
   */
  public function redirection(NodeInterface $node, object $config, array $session) {

    // Check to make sure the nid passed in belongs to REFDB_BUNDLE.
    if ($node->bundle() != static::REFDB_BUNDLE) {
      $front_page = Url::fromRoute('<front>')->toString();
      return new TrustedRedirectResponse($front_page);
    }

    // React on too many wrong entries by user.
    if (isset($session['acld_refdb_cardtries']) && $session['acld_refdb_cardtries'] > 3) {
      $nid = $config->get('aclib_refdb_failurenodeid');
      $fallback_node = Url::fromRoute('entity.node.canonical', ['node' => $nid]);
      return new TrustedRedirectResponse($fallback_node->toString());
    }

    // If debugging is on use that ip, otherwise get current user's ip.
    $user_ip = $config->get('aclib_refdb_debug') && !empty($config->get('aclib_refdb_debug_ip')) ? $config->get('aclib_refdb_debug_ip') : $this->requestStack->getClientIp();

    // Determine if the user is internal or external to the ACLIB network.
    $hq_only_field = $node->hasField(static::HQ_FIELD) && !empty($node->get(static::HQ_FIELD)->getValue()) ? $node->get(static::HQ_FIELD)->getValue()[0] : [];
    $hq_only_field_value = isset($hq_only_field['value']) && $hq_only_field['value'] > 0 ? TRUE : FALSE;

    $is_user_on_site = $hq_only_field_value ? $this->multilineMatch($config->get('aclib_refdb_hqips'), $user_ip) : $this->multilineMatch($config->get('aclib_refdb_internalips'), $user_ip);

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

    // Send off-site users to the external URL for HQ-only DBs.
    if (!$is_user_on_site && $hq_only_field_value && $external_url_value) {
      return new TrustedRedirectResponse($external_url_value->toString());
    }

    // Determine if the DB requires sign on and handle appropriately.
    if (isset($sign_on['value']) && ($sign_on['value'] != 1)) {
      if ($is_user_on_site && $internal_url_value) {
        $data['location'] = 0;
        // Create instance of our custom logging entity.
        $this->logAccess($data);
        // Return redirect.
        return new TrustedRedirectResponse($internal_url_value->toString());
      }
      else {
        if ($external_url_value) {
          // Create instance of our custom logging entity.
          $data['location'] = 1;
          $this->logAccess($data);
          // Return redirect.
          return new TrustedRedirectResponse($external_url_value->toString());
        }
      }
    }

    // If the user is on_site, send them on their way.
    if ($is_user_on_site && $internal_url_value) {
      $data['location'] = 0;
      // Create instance of our custom logging entity.
      $this->logAccess($data);
      // Return redirect.
      return new TrustedRedirectResponse($internal_url_value->toString());
    }

    // See if they have already been verified previously
    // Send them to the external url if they've logged in before and
    // their cookie is still valid, otherwise give them the library card form.
    if (isset($session['cardVerified']) && $session['cardVerified'] > 0 && $external_url_value) {
      // Create instance of our custom logging entity.
      $data['location'] = 1;
      $this->logAccess($data);
      // Return redirect.
      return new TrustedRedirectResponse($external_url_value->toString());
    }
  }

}
