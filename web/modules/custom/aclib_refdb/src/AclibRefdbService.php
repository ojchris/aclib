<?php

namespace Drupal\aclib_refdb;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
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

  const LOGGING_VIEWS = [
    'aclib_ref_db' => [
      'display_id' => 'page_1',
    ],
  ];

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
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  public $entityFieldManager;

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
  public function __construct(RequestStack $request_stack, ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager, PrivateTempStoreFactory $private_temp_store, LoggerChannelFactoryInterface $logger, MessengerInterface $messenger) {
    $this->requestStack = $request_stack->getCurrentRequest();
    $this->config = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
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
      $container->get('entity_field.manager'),
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
      $front_page = Url::fromRoute('<front>');
      return $this->doRedirect($front_page);
    }

    // React on too many wrong entries by user.
    if (isset($session['acld_refdb_cardtries']) && $session['acld_refdb_cardtries'] > 3) {
      $nid = $config->get('aclib_refdb_failurenodeid');
      $fallback_node = Url::fromRoute('entity.node.canonical', ['node' => $nid]);
      return $this->doRedirect($fallback_node);
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
      return $this->doRedirect($external_url_value);
    }

    // Determine if the DB requires sign on and handle appropriately.
    if (isset($sign_on['value']) && ($sign_on['value'] != 1)) {
      if ($is_user_on_site && $internal_url_value) {
        $data['location'] = 'internal';
        // Create instance of our custom logging entity.
        $this->logAccess($data);
        // Return redirect.
        return $this->doRedirect($internal_url_value);
      }
      else {
        if ($external_url_value) {
          // Create instance of our custom logging entity.
          $data['location'] = 'external';
          $this->logAccess($data);
          // Return redirect.
          return $this->doRedirect($external_url_value);
        }
      }
    }

    // If the user is on_site, send them on their way.
    if ($is_user_on_site && $internal_url_value) {
      $data['location'] = 'internal';
      // Create instance of our custom logging entity.
      $this->logAccess($data);
      // Return redirect.
      return $this->doRedirect($internal_url_value);
    }

    // See if they have already been verified previously
    // Send them to the external url if they've logged in before and
    // their cookie is still valid, otherwise give them the library card form.
    if (isset($session['cardVerified']) && $session['cardVerified'] > 0 && $external_url_value) {
      // Create instance of our custom logging entity.
      $data['location'] = 'external';
      $this->logAccess($data);

      // Return redirect.
      return $this->doRedirect($external_url_value);
    }
  }

  /**
   * Invalidate Drupal cache for card form and redirections.
   *
   * @param \Drupal\Core\Url $url
   *   Internal or External redirection URL object.
   * @param bool $cache
   *   Set to TRUE to involve Drupal's cache.
   *
   * @return \Drupal\Core\Routing\TrustedRedirectResponse
   *   TrustedRedirectResponse object to execure actual redirection.
   */
  public function doRedirect(Url $url, bool $cache = FALSE) {

    // Define our redirect.
    $response = new TrustedRedirectResponse($url->toString());

    // Take care of some cache here.
    if (!$cache) {
      $metadata = $response->getCacheableMetadata();
      $metadata->setCacheMaxAge(0);
    }
    return $response;
  }

  /**
   * Fetch card patterns from main config and return as array.
   *
   * @param mixed $parent_label
   *   Mostly a string for creation <optiongroup> on field config.
   *
   * @return array
   *   An associative array with card patterns,
   *   optionally nested within a parent $option_label array.
   */
  public function getPatterns($parent_label = NULL) {
    $config = $this->config->get('aclib_refdb.settings');
    $patterns_matched = $config->get('aclib_refdb_card_accept');
    $patterns = [];
    if (!empty($patterns_matched)) {
      $patterns_matched_array = preg_split('/\r\n|[\r\n]/', $patterns_matched);
      if (!empty($patterns_matched_array)) {
        // ksm($parent_label);
        if ($parent_label) {
          $patterns[$parent_label] = [];
        }
        foreach ($patterns_matched_array as $pattern) {
          if ($parent_label) {
            $patterns[$parent_label][$pattern] = $pattern;
          }
          else {
            $patterns[$pattern] = $pattern;
          }
        }
      }
    }
    return $patterns;
  }

  /**
   * Programmatically update Aclib RefDb views.
   *
   * @param array $patterns
   *   Contains an array with card patterns.
   */
  public function updateViews(array $patterns = []) {

    if (empty($patterns)) {
      $patterns = $this->getPatterns();
    }

    foreach (static::LOGGING_VIEWS as $view_id => $data) {

      $view_storage = $this->entityTypeManager->getStorage('view')->load($view_id);
      $view = $view_storage->getExecutable();
      $view->setDisplay($data['display_id']);
      $style = $view->display_handler->getPlugin('style');

      if (!empty($patterns)) {

        $existing_patterns = $view->getHandlers('field', $data['display_id']);
        foreach (array_keys($existing_patterns) as $existing_pattern_id) {
          if (strpos($existing_pattern_id, 'patterns_') !== FALSE) {
            $view->removeHandler($data['display_id'], 'field', $existing_pattern_id);
          }
        }

        foreach ($patterns as $pattern) {
          $alter = [
            'text' => t('No results found or error occured'),
          ];
          $field_data = [
            'id' => 'aclib_refdb_computed_field',
            'class' => 'Drupal\aclib_refdb\Plugin\views\field\AclibRefDbComputedField',
            'table' => 'aclib_refdb_logs',
            'property' => $pattern,
            'alter' => $alter,
            'label' => $pattern,
          ];
          $pattern_clean = str_replace('*', '', $pattern);
          $view->addHandler($data['display_id'], 'field', 'aclib_refdb_logs', 'computed', $field_data, 'patterns_' . $pattern_clean);

          // Set sortable flag for table based display styles
          // Does NOT work.
          $style->options['info']['patterns_' . $pattern_clean] = [
            'sortable' => 1,
            'default_sort_order' => 'asc',
            'align' => '',
            'separator' => '',
            'empty_column' => 0,
            'responsive' => '',
          ];
        }

        // Set sortable flag for table based display styles.
        // Does NOT work.
        $view->display_handler->options['style']['options'] = $style->options;

        // Now save View entity with new handlers and options set.
        $view->save();
      }
    }
  }

  /**
   * An effort to control counting keys as dynamic.
   *
   * @param bool $is_form
   *   If true we make option labels more readable.
   *
   * @return array
   *   An associative array ready for <select> form element's options
   *   in field config in a View, or as a base array for a render method.
   */
  public function defaultCountOptions(bool $is_form = FALSE) {

    $config = $this->config->get('aclib_refdb.settings');
    $internal = is_array($config->get('aclib_refdb_internal')) ? $config->get('aclib_refdb_internal') : [];
    if (!empty($internal)) {
      $base_fields = $this->entityFieldManager->getBaseFieldDefinitions('aclib_refdb_logs');
      foreach ($internal as $base_field => $options) {
        if (in_array($base_field, array_keys($base_fields))) {

          $options_label = $is_form ? ucfirst(str_replace('_', ' ', $base_field)) : $base_field;
          if ($options) {
            $properties[$options_label] = [];
            // Static map/values defined in aclib_refdb.settings.yml
            // as"aclib_refdb_internal" property.
            foreach ($options as $option_key => $option_label) {
              $properties[$options_label][$option_key] = $this->t('@option_label', ['@option_label' => $option_label]);
            }
          }
        }
      }
    }
    return $properties;
  }

  /**
   * Counting aclib refdb logs properties entityQuery.
   *
   * @param string $base_field
   *   Base field name.
   * @param int $nid
   *   Reference DB node node.
   * @param mixed $value
   *   A counting value or ALL.
   */
  public function defaultCountQuery(string $base_field, int $nid, $value = NULL) {
    $aclib_refdb_storage = $this->entityTypeManager->getStorage('aclib_refdb_logs');
    $aclib_refdb_storage_count = $aclib_refdb_storage->getQuery()
      ->condition('nid', $nid)
      ->groupBy('nid');
    // ->aggregate('nid', 'COUNT')->count();
    // ->addTag('debug')
    if ($value != NULL) {
      $aclib_refdb_storage_count->condition($base_field, $value);
    }
    return $aclib_refdb_storage_count->count();
  }

  /**
   * A query string exoression that should work for counting our properties.
   *
   * @param string $field_name
   *   Base field name, a column in aclib_refdb_logs table.
   * @param mixed $value
   *   Matching field's value.
   *
   * @rerurn string - SQL query string
   */
  public function queryCountProperty(string $field_name, $value = NULL) {
    $query_string = "(SELECT COUNT(aclib_refdb_logs." . $field_name . ") FROM aclib_refdb_logs WHERE aclib_refdb_logs.nid = node_field_data_aclib_refdb_logs.nid";
    $query_string .= !$value ? ")" : " AND aclib_refdb_logs." . $field_name . " = '" . $value . "')";
    return $query_string;
  }

}
