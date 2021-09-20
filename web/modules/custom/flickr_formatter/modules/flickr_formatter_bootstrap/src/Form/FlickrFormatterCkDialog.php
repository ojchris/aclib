<?php

namespace Drupal\flickr_formatter_bootstrap\Form;

use Symfony\Component\DependencyInjection\ContainerInterface;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Component\Utility\Html;
use Drupal\editor\Ajax\EditorDialogSave;

use Drupal\flickr_formatter\FlickrFormatterService;

/**
 * Creates a dialog form for use in CKEditor.
 *
 * @package Drupal\flickr_formatter_bootstrap\Form
 */
class FlickrFormatterCkDialog extends FormBase {

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * Renderer interface.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Instantiate our main service.
   *
   * @var \Drupal\flickr_formatter\FlickrFormatterService
   */
  protected $flickrFormatterService;

  /**
   * GridDialog constructor.
   *
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   Rendered interface.
   * @param \Drupal\flickr_formatter\FlickrFormatterService $flickr_formatter_service
   *   Base module service.
   */
  public function __construct(FormBuilderInterface $form_builder, RendererInterface $renderer, FlickrFormatterService $flickr_formatter_service) {
    $this->formBuilder = $form_builder;
    $this->renderer = $renderer;
    $this->flickrFormatterService = $flickr_formatter_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('form_builder'),
      $container->get('renderer'),
      $container->get('flickr_formatter.service')
    );
  }

  /**
   * {@inheritDoc}
   */
  public function getFormId() {
    return 'flickr_formatter_dialog';
  }

  /**
   * {@inheritDoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    // Attach mandatory library.
    $form['#attached']['library'][] = 'editor/drupal.editor.dialog';

    $user_input = $form_state->getUserInput();
    $input = isset($user_input['editor_object']) ? $user_input['editor_object'] : [];

    $form['#tree'] = TRUE;
    $form['#attached']['library'][] = 'editor/drupal.editor.dialog';
    $form['#prefix'] = '<div id="editor-flickr-dialog-form">';
    $form['#suffix'] = '</div>';

    $form['flickr_id'] = [
      '#type' => 'textfield',
      '#default_value' => isset($input['flickr_id']) ? $input['flickr_id'] : '',
      '#title' => t('Flickr ID'),
      '#required' => TRUE,
    ];

    // Usage of our Element plugin.
    // It is defined as such because we may need to repeat that form
    // at some other places, not only here.
    $form['flickr_base'] = [
      '#type' => 'flickr_formatter_base',
      '#title' => t('Flickr_formatter settings'),
      '#default_value' => isset($input['flickr_base']) ? $input['flickr_base'] : [],
    ];

    $form['bootstrap_grid'] = [
      '#type' => 'fieldset',
      '#title' => t('Bootstrap Grid settings'),
      '#description' => t('The most relevant <a href="https://getbootstrap.com/docs/5.1/layout/grid/#grid-options">Bootstrap Grid</a> options.'),
      '#states' => [
        'visible' => [
          ':input[id="flickr-formatter-type"]' => ['!value' => 'photo'],
          ':input[id="flickr-formatter-style"]' => ['value' => 'bootstrap_grid'],
        ],
      ],
    ];

    $form['bootstrap_grid']['value'] = [
      '#type' => 'flickr_formatter_bootstrap_grid',
      '#default_value' => isset($input['bootstrap_grid']) && isset($input['bootstrap_grid']['value']) ? $input['bootstrap_grid']['value'] : [],
    ];

    $form['bootstrap_carousel'] = [
      '#type' => 'fieldset',
      '#title' => t('Bootstrap Carousel settings'),
      '#description' => t('The most relevant <a href="https://getbootstrap.com/docs/5.1/components/carousel/#options">Bootstrap 5 Carousel</a> options.'),
      '#states' => [
        'visible' => [
          ':input[id="flickr-formatter-type"]' => ['!value' => 'photo'],
          ':input[id="flickr-formatter-style"]' => ['value' => 'bootstrap_carousel'],
        ],
      ],
    ];

    $bootstrap_carousel_default_value = \Drupal::service('flickr_formatter.service')->getThirdPartySettings('flickr_formatter_bootstrap', 'bootstrap_carousel', []);
    $form['bootstrap_carousel']['value'] = [
      '#type' => 'flickr_formatter_bootstrap_carousel',
      '#default_value' => isset($input['bootstrap_carousel']) && isset($input['bootstrap_carousel']['value']) ? $input['bootstrap_carousel']['value'] : [],
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['save_dialog'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#button_type' => 'primary',
      // No regular submit-handler. This form only works via JavaScript.
      '#submit' => [],
      '#ajax' => [
        'callback' => '::submitForm',
        'event' => 'click',
      ],
      '#attributes' => [
        'class' => [
          'js-button-next',
        ],
      ],
    ];

    // Return parent::buildForm($form, $form_state);.
    return $form;
  }

  /**
   * Commit the changes and close the dialog window.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An ajax response.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $response = new AjaxResponse();
    static $count = 0;
    $count++;

    if ($form_state->getErrors()) {
      unset($form['#prefix'], $form['#suffix']);
      $form['status_messages'] = [
        '#type' => 'status_messages',
        '#weight' => -10,
      ];
      $response->addCommand(new HtmlCommand('#editor-flickr-dialog-form', $form));
    }
    else {

      $values = $form_state->getValues();
      if (isset($values['flickr_base']['style'])) {

        $style = $values['flickr_base']['style'];
        $images = $this->flickrFormatterService->processGroup(['id' => $values['flickr_id']], $values['flickr_base'], $values[$style]);
        if (isset($images['#options']['value'])) {
          $images['#options'] += $images['#options']['value'];
        }

        $images['#options']['flickr_id'] = $values['flickr_id'];
        $images['#options']['id'] = Html::getUniqueId('flickr-bootstrap-carousel-' . $count);

        $values['style'] = '<div class="flickr-formatter-inline">' . $this->renderer->render($images)->__toString() . '</div>';
        $css = drupal_get_path('module', 'flickr_formatter') . '/css/flickr_formatter.css';
        $values['style'] .= '<link rel="stylesheet" media="all" href="' . $css . '">';

        $response->addCommand(new EditorDialogSave($values));
        $response->addCommand(new CloseModalDialogCommand());
      }
      else {
        unset($form['#prefix'], $form['#suffix']);
        $form['status_messages'] = [
          '#type' => 'status_messages',
          '#weight' => -10,
        ];
        $response->addCommand(new HtmlCommand('#editor-flickr-dialog-form', $form));
      }
    }
    return $response;
  }

}
