<?php

namespace Drupal\flickr_formatter\Plugin\Field\FieldFormatter;

use Symfony\Component\DependencyInjection\ContainerInterface;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;

use Drupal\Component\Utility\Html;

use Drupal\flickr_formatter\FlickrFormatterService;

/**
 * Plugin implementation of the 'Flickr' formatter for text fields.
 *
 * @FieldFormatter(
 *   id = "flickr_field_formatter",
 *   label = @Translation("Flickr"),
 *   field_types = {
 *     "integer",
 *     "string",
 *     "string_long"
 *   }
 * )
 */
class FlickrFieldFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  /**
   * Instantiate our main service.
   *
   * @var Drupal\flickr_formatter\FlickrFormatterService
   */
  protected $flickrFormatterService;

  /**
   * {@inheritdoc}
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, FlickrFormatterService $flickr_formatter_service) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->flickrFormatterService = $flickr_formatter_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('flickr_formatter.service')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    $settings = [
      'type' => 'photo',
      'per_page' => NULL,
      'size' => '-',
      'caption' => NULL,
      'link' => NULL,
      'max_width' => NULL,
      'title' => NULL,
      'classes' => NULL,
      'style' => 'default',
    ];
    return $settings + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {

    $form = parent::settingsForm($form, $form_state) + [
      '#type' => 'flickr_formatter_base',
      '#title' => t('Flickr_formatter base settings'),
      '#default_value' => $this->getSettings(),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {

    $summary = parent::settingsSummary();

    $default_options = $this->flickrFormatterService->getDefaultOptions();

    if ($this->getSetting('per_page')) {
      $summary[] = $this->t('Pager: @per_page', ['@per_page' => $this->getSetting('per_page')]);
    }

    $size_options = $this->flickrFormatterService->getSizesOptions();
    $size_label = isset($size_options[$this->getSetting('size')]) ? $size_options[$this->getSetting('size')] : NULL;
    if ($size_label) {
      $summary[] = $this->t('Image size: @size', ['@size' => $size_label]);
    }

    if ($this->getSetting('caption')) {
      $summary[] = $this->t('Show image caption: Yes');
    }

    if ($this->getSetting('link')) {
      $summary[] = $this->t('Link to Flickr page: Yes');
    }

    if ($this->getSetting('max_width')) {
      $summary[] = $this->t('Max width: Yes');
    }

    if ($title = $this->getSetting('title')) {
      $summary[] = $this->t('Title: @title', ['@title' => $title]);
    }

    if ($classes = $this->getSetting('classes')) {
      $summary[] = $this->t('CSS classes: @classes', ['@classes' => $classes]);
    }

    $type_label = isset($default_options['type'][$this->getSetting('type')]) ? $default_options['type'][$this->getSetting('type')] : NULL;
    if ($type_label) {
      $summary[] = $this->t('Type: @type', ['@type' => $type_label]);
    }

    if ($this->getSetting('type') != 'photos' && $this->getSetting('style')) {
      $style_label = isset($default_options['style'][$this->getSetting('style')]) ? $default_options['style'][$this->getSetting('style')] : NULL;
      if ($style_label) {
        $summary[] = $this->t('Style: @style', ['@style' => $style_label]);
      }
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {

    $elements = [];

    $type = $this->getSetting('type');
    $style = $this->getSetting('style');

    // Here we check on existing sub-modules,
    // in theory those should be only for particular styles/themes.
    $third_party_settings = [];
    $submodules = $this->flickrFormatterService->getSubmodules();

    if (!empty($submodules)) {
      foreach ($submodules as $submodule) {
        $third_party_settings += $this->getThirdPartySetting($submodule, $style, []);
      }
      $third_party_settings = isset($third_party_settings['value']) ? $third_party_settings['value'] : $third_party_settings;

      // Define unique ID for usage in twig template eventually.
      // Such as wrapper element id for bootstrap type of markup.
      if (!empty($third_party_settings)) {
        $field_name = $this->fieldDefinition->getName();
        $parent_entity = $items->getEntity();
        $third_party_settings['id'] = Html::getUniqueId('flickr-bootstrap-' . $parent_entity->id() . '-' . $field_name);
      }
    }

    foreach ($items as $delta => $item) {

      switch ($type) {
        case 'photo':
          $elements[$delta] = [
            '#theme' => 'flickr_formatter_image',
            '#image' => $this->flickrFormatterService->processSingle(['id' => (int) $item->value], $this->getSetting('size'), $this->getSetting('title')),
          ];
          break;

        default:
          $elements[$delta] = $this->flickrFormatterService->processGroup(['id' => $item->value], $this->getSettings(), $third_party_settings);
          break;
      }
    }
    return $elements;
  }

}
