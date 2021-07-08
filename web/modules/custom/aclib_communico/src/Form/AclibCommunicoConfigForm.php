<?php

/**
 * @file
 * Contains \Drupal\aclib_communico\Form\AclibCommunicoConfigForm.
 */

namespace Drupal\aclib_communico\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Cache\CacheBackendInterface;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\FormStateInterface;

use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\Html;

use Drupal\aclib_communico\AclibCommunicoClient;

class AclibCommunicoConfigForm extends ConfigFormBase {

  /**
   * Drupal\Core\Config\ConfigFactoryInterface definition.
   * 
   * @var Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $cache;

  /**
   * Drupal\aclib_communico\AclibCommunicoClient definition.
   * 
   * @var Drupal\aclib_communico\AclibCommunicoClient
   */
  protected $client;

  /**
   * Constructs a \Drupal\system\ConfigFormBase object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   */
  public function __construct(ConfigFactoryInterface $config_factory, CacheBackendInterface $cache, AclibCommunicoClient $aclib_communico_client) {
    parent::__construct($config_factory);
    $this->cache = $cache;
    $this->client = $aclib_communico_client;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('cache.default'),
      $container->get('aclib_communico.client')
    );
  }
  
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'aclib_communico_config_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'aclib_communico.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form = parent::buildForm($form, $form_state);

    $config = $this->config('aclib_communico.settings');
    
    $form['communico_api'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Communico API settings'),
      '#description' => $this->t('Check documentation on <a href="http://api.communico.co/docs/" target="_blank">Communico API</a>'),
      '#tree' => TRUE,
    ];

    // Credentials
    $form['communico_api']['access_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Access Key'),
      '#default_value' => $config->get('access_key'),
      '#required' => TRUE,
    ];
   
    $form['communico_api']['secret_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Secret Key'),
      '#default_value' => $config->get('secret_key'),
      '#required' => TRUE,
      '#ajax' => [
        'event' => 'change',
        'callback' => [get_class($this), 'getEventTypes'],
        'effect' => 'fade',
        'wrapper' => 'aclib-communico-event-types',
        'progress' => [
          'type' => 'throbber',
          'message' => t('Requesting Event types from Communico...'),
        ],
      ], 
    ];

    $form['communico_api']['token_endpoint'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Access token API endpoint'),
      '#default_value' => $config->get('token_endpoint'),
      '#required' => TRUE,
    ];

    $form['communico_api']['types_endpoint'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Event types API endpoint'),
      '#default_value' => $config->get('types_endpoint'),
      '#required' => TRUE,
    ];

    $form['communico_api']['events_endpoint'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Events API endpoint'),
      '#default_value' => $config->get('events_endpoint'),
      '#required' => TRUE,
    ];

    // Communico API credentials were entered for the first time (or from previously blank), store it in our config object
    $guzzle_options = $config->get('guzzle_options');
    $save = FALSE;
    $access_key = $config->get('access_key');
    $secret_key = $config->get('secret_key');
    $base_uri = $guzzle_options['base_uri'];

    if (!$access_key && !empty($form_state->getValue(['communico_api', 'access_key']))) {
      $access_key = $form_state->getValue(['communico_api', 'access_key']);
      $config->set('access_key', $access_key);
      $save = TRUE;
    }    
    if (!$secret_key && !empty($form_state->getValue(['communico_api', 'secret_key']))) {
      $secret_key = $form_state->getValue(['communico_api', 'secret_key']);
      $config->set('secret_key', $secret_key);
      $save = TRUE; 
    } 
    if (empty($base_uri) && !empty($form_state->getValue(['guzzle_options', 'base_uri']))) {
      $base_uri = $form_state->getValue(['guzzle_options', 'base_uri']);
      $config->set('base_uri', $base_uri);
      $save = TRUE; 
    } 
    // So save it "on the fly" after ajax api call response
    if ($save) {
      $config->save();
    }

    // Get values (options) for Event type select field
    // Note, it's intentional to not call saved values $config->get('types') as first because we indeed may want to refresh this list coming from Remote API, for changes made there
    // Then, we set this in default Drupal cache to avoid request every time - for refreshed list from remote API - drush cr and reload page
    $event_types = NULL;
    if ($cache = $this->cache->get('aclib_communico_event_types')) {
      $event_types = $cache->data;
    }
    else {

      if ($access_key && $secret_key && $config->get('types_endpoint') && !empty($base_uri)) {

        $status = $this->t('Access token valid, connection with API successful.');
        $this->messenger()->addStatus($status);
       
        $get_types = $this->client->get($config->get('types_endpoint'));
        if (is_array($get_types) && isset($get_types['data']) && isset($get_types['data']['entries']) && !empty($get_types['data']['entries'])) {
          foreach ($get_types['data']['entries'] as $entry) {
            $entry_key = str_replace('-', '_', Html::getId($entry['name']));
            if ($entry_key) {
              $event_types[$entry_key] = $entry['name'];
            }
          }

          // Set this into cache
          $this->cache->set('aclib_communico_event_types', $event_types);
        }
      }
    } 
    
    // Dates, from-to period filter for Communico API request
    $form['communico_api']['startDate'] = [
      '#type' => 'date',
      '#title' => $this->t('Start date'),
      '#description' => $this->t('Select start date here. It is equivalent to "startDate" field for Communico API request.'),
      '#default_value' => $config->get('startDate'),
    ];

    $form['communico_api']['endDate'] = [
      '#type' => 'date',
      '#title' => $this->t('End date'),
      '#description' => $this->t('Select end date. It is equivalent to "endDate" field for Communico API request.'),
      '#default_value' => $config->get('endDate'),
    ];

    // First check for default values for event types
    $types_default_value = $config->get('types') ? array_keys($config->get('types')) : NULL;

    $form['communico_api']['types'] = [
      '#type' => 'select',
      '#title' => $this->t('Event types'),
      '#description' => $this->t('Select a type of event we\'re filtering for'),
      '#default_value' => is_array($types_default_value) && $types_default_value[0] ? $types_default_value : NULL,
      '#options' => $event_types,
      '#multiple' => TRUE,
      '#empty_option' => $this->t('- Select -'),
      '#prefix' => '<div id="aclib-communico-event-types">',
      '#suffix' => '</div>' 
    ];

    $form['communico_api']['limit'] = [
      '#type' => 'number',
      '#title' => $this->t('Limit'),
      '#description' => $this->t('A maximum number of items to request from Communico API. It is "limit" parameter, see on <a href="https://api.communico.co/docs/#!/attend/get_v3_attend_events" target="_blank">API page</a>'),
      '#default_value' => $config->get('limit'),
    ];

    $form['guzzle_options'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Guzzle options'),
      '#description' => $this->t('A possible options for GuzzleHttp. See it on <a href="https://docs.guzzlephp.org/en/stable/request-options.html" target="_blank">Guzzle docs</a> for more info.'),
      '#tree' => TRUE,
    ];

    $form['guzzle_options']['base_uri'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Communico API Base URI'),
      '#default_value' => $guzzle_options['base_uri'],
      '#required' => TRUE,
    ];
  
    $form['guzzle_options']['timeout'] = [
      '#type' => 'number',
      '#title' => $this->t('Timeout'),
      '#default_value' => $guzzle_options['timeout'],
    ];

    $form['debug'] = [ 
      '#type' => 'checkbox',
      '#title' => t('Debug'),
      '#description' => t('If this is set/checked there will be no new nodes created or updated. Yet, all the other operations towards API do run. Also important, when this is checked any leftover (stuck) tasks in QueueWorker will be deleted so to start all over.'), 
      '#default_value' => $config->get('debug'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $config = $this->config('aclib_communico.settings');
    
    $event_types = NULL;
    if ($cache = $this->cache->get('aclib_communico_event_types')) {
      $event_types = $cache->data;
    }

    foreach ($form_state->getValues() as $value_key => $value) {
      if ($value_key == 'guzzle_options') {
        $guzzle_options = [];
        foreach ($value as $v_key => $v) {
          $guzzle_options[$v_key] = $v; 
          $config->set($value_key, $guzzle_options);
        }
      }
      else if ($value_key == 'communico_api') {
        foreach ($value as $v_key => $v) {
          // We need to save the actual label for event type too
          if ($v_key == 'types') {
            $types = [];
            foreach ($v as $type) {
              $types[$type] = $event_types ? $event_types[$type] : $type;
            }
            $config->set($v_key, $types);
          }
          else {
            $config->set($v_key, $v);
          }
        }
      }
      else if ($value_key == 'debug') {
        $config->set($value_key, $value);
      }
    }
    $config->save();

    return parent::submitForm($form, $form_state);
  }

  /**
   * Ajax callback - upon first time entering API credentials
   */
  public static function getEventTypes(&$form, FormStateInterface $form_state) {
    $element = NestedArray::getValue($form, ['communico_api', 'types']);
    return $element; 
  }
}