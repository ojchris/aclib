<?php

namespace Drupal\aclib_plugins\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\Html;

/**
 * Provides block with links to social networks.
 *
 * @Block(
 *   id = "aclib_plugins_search_block",
 *   admin_label = @Translation("Tabbed search"),
 *   category = @Translation("ACLIB")
 * )
 */
class AclibSearchInputBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'labels' => [],
      'actions' => [],
      'placeholders' => [],
      'button' => NULL,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
  
    $form = parent::blockForm($form, $form_state);
    $config = $this->getConfiguration();
    
    // Gather the number of referenced views in the form already.
    $tabs_number = $form_state->get('tabs_number');
    // We have to ensure that there is at least one widget
    if ($tabs_number === NULL) {
      if (count($config['actions']) > 1) {
        $tabs_number = count($config['actions']);
      }
      else {
        $tabs_number = 1;
      }
    }

    $form_state->set('tabs_number', $tabs_number);

    // Container for our tabs related items
    $form['tabs_items'] = [
      '#type' => 'fieldset',
      '#attributes' => [
        'id' => 'aclib-plugins-tabs-wrapper',
      ]
    ];

    for ($delta = 1; $delta <= $tabs_number; $delta++) {
    
      $index = $delta - 1;

      $form['tabs_items'][$index]['labels'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Tab label'),
        '#description' => $this->t('Set label for a tab, leave blank for empty (i.e. one search input - no tabs situation.'),
        '#default_value' => isset($config['labels'][$index]) ? $config['labels'][$index] : NULL,
       ];


      $form['tabs_items'][$index]['actions'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Search action URL'),
        '#description' => $this->t('A full formatted absolute URL, for any possible external service/page, or a path to a designated search page. In this format: <em>/search/%1</em> or with url query params like <em>/search?key=%1</em>, or combined url argument and query params. Same for both, absolute URLs and local paths.'),
        '#default_value' => isset($config['actions'][$index]) ? $config['actions'][$index] : NULL,
        '#required' => TRUE,
       ];

       $form['tabs_items'][$index]['placeholders'] = [
         '#type' => 'textfield',
         '#title' => $this->t('Placeholder'),
         '#description' => $this->t('A placeholder attribute for search input, leave empty for none.'), 
         '#default_value' => isset($config['placeholders'][$index]) ? $config['placeholders'][$index] : NULL,
       ];
     }

     $form['tabs_items']['add_tab'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add tab'),
      '#limit_validation_errors' => [],
       '#submit' => [
        [get_class($this), 'addTabSubmit'],
      ],
      '#weight' => 20, 
      '#ajax' => [
        'callback' => [get_class($this), 'ajaxCallback'],
        'wrapper' => 'aclib-plugins-tabs-wrapper',
      ],
    ];

    if ($tabs_number > 1) {
      $form['tabs_items']['remove_tab'] = [
        '#type' => 'submit',
        '#value' => $this->t('Remove tab'),
        '#limit_validation_errors' => [],
        '#submit' => [
          [get_class($this), 'removeTabSubmit'],
        ],
        '#weight' => 20, 
        '#ajax' => [
          'callback' => [get_class($this), 'ajaxCallback'],
          'wrapper' => 'aclib-plugins-tabs-wrapper',
        ],
      ];
    }
    
    $form['button'] = [
      '#type' => 'textfield',
      '#title' => t('Icon/Button'),
      '#description' => $this->t('For web font icons. For example, for bootstrap web font loupe icon is called <em>search</em>. See <em>aclib-plugins-search-block.html.twig</em>'),
      '#default_value' => $config['button']
    ];
   
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    
    parent::blockSubmit($form, $form_state);
  
    if (!empty($form_state->getValue('tabs_items'))) {
      foreach ($form_state->getValue('tabs_items') as $index => $tabs_item) {
        $this->configuration['labels'][$index] = $tabs_item['labels'];
        $this->configuration['actions'][$index] = $tabs_item['actions'];
        $this->configuration['placeholders'][$index] = $tabs_item['placeholders'];
      }
    }
    $this->configuration['button'] = $form_state->getValue('button');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {

    $config = $this->getConfiguration();

    $build = [
      '#theme' => 'aclib_plugins_search_block',
      '#button' => $config['button'],
      '#tabs' => [],
    ];

    // If we are on some kind of search page route - set default value (keyword) on search input
    $has_query = \Drupal::request()->query->get('keyword'); 
    $route = \Drupal::service('current_route_match');
    $is_view = $route->getParameters()->has('view_id') ? $route->getParameter('view_id') : NULL;
    $has_argument = $is_view && $route->getParameters()->has('arg_0') ? $route->getParameter('arg_0') : NULL;

    foreach ($config['actions'] as $index => $action) {
      if (!empty($action)) {

        // Check on default value for search input
        $default_value = $has_query ? $has_query : $has_argument;
   
        $label = !empty($config['labels'][$index]) ? $config['labels'][$index] : $this->t('Missing tab label');
          
        $build['#tabs'][$index] = [
          'search_input' => [
            '#type' => 'search',
            '#theme_wrappers' => [],
            '#default_value' =>$default_value,
            '#attributes' => [
              'id' => 'aclib-plugins-search-input-wrapper-' . $index,
              'placeholder' => !empty($config['placeholders'][$index]) ? $config['placeholders'][$index] : NULL
            ],
          ], 
          'id' => Html::getId($label),
          'action' => $action,
          'label' => $label,
        ];
      }
    }

    // Attach that jQuery code too
    $build['#attached']['library'][] = 'aclib_plugins/search_block'; 

    return $build;
  }

  /**
   * Callback for all ajax actions.
   *
   * Returns parent container element for each group
   */
  public static function ajaxCallback(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    $parents = array_slice($trigger['#parents'], 0, -1);
    $element = NestedArray::getValue($form, $parents);
    return $element; 
  }

  /**
   * "Add View" Submit callback
   */
  public static function addTabSubmit(array &$form, FormStateInterface $form_state) {
    $tabs_number = $form_state->get('tabs_number');
    $delta = $tabs_number + 1;
    $form_state->set('tabs_number', $delta);
    $form_state->setRebuild(TRUE);
  }

  /**
   * "Remove View" Submit callback
   */
  public static function removeTabSubmit(array &$form, FormStateInterface $form_state) {
    $tabs_number = $form_state->get('tabs_number');
    $delta = $tabs_number - 1;
    $form_state->set('tabs_number', $delta);
    $form_state->setRebuild(TRUE);
  }

  /*
  public function getCacheContexts() {
    // If we depend on \Drupal::routeMatch() we should set context of this block with 'route' context tag.
    // Every new route this block will rebuild
    return Cache::mergeContexts(parent::getCacheContexts(), ['route', 'url']);
  }
  */
}