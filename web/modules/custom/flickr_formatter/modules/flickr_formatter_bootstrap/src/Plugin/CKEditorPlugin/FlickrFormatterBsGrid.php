<?php

namespace Drupal\flickr_formatter_bootstrap\Plugin\CKEditorPlugin;

use Drupal\ckeditor\CKEditorPluginBase;
use Drupal\ckeditor\CKEditorPluginConfigurableInterface;
use Drupal\ckeditor\CKEditorPluginCssInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\editor\Entity\Editor;

/**
 * Defines the "flickr_formatter_bs_grid" plugin.
 *
 * @CKEditorPlugin(
 *   id = "flickr_formatter_bs_grid",
 *   label = @Translation("Flickr formatter")
 * )
 */
class FlickrFormatterBsGrid extends CKEditorPluginBase implements CKEditorPluginConfigurableInterface, CKEditorPluginCssInterface {

  /**
   * {@inheritdoc}
   */
  public function getButtons() {
    $path = drupal_get_path('module', 'flickr_formatter_bootstrap') . '/js/plugins/flickr_formatter_bs_grid';

    return [
      'flickr_formatter_bs_grid' => [
        'label' => 'Flickr formatter',
        'image' => $path . '/icons/flickr_formatter_bs_grid.png',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFile() {
    return drupal_get_path('module', 'flickr_formatter_bootstrap') . '/js/plugins/flickr_formatter_bs_grid/plugin.js';
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig(Editor $editor) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state, Editor $editor) {
    $settings = $editor->getSettings();
    $config = $settings['plugins']['flickr_formatter_bs_grid'] ?? [];
    $form['use_cdn'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use BS CDN'),
      '#description' => $this->t('If your theme utilizing CKEditor does not include bootstrap grid classes, or pass them via "ckeditor_stylesheets" then you can include it here. This will ONLY include it for ckeditor.'),
      '#default_value' => $config['use_cdn'] ?? TRUE,
    ];

    $form['cdn_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('CDN URL'),
      '#description' => $this->t('The URL to your Bootstrap CDN, default is for grid-only.'),
      '#default_value' => $config['cdn_url'] ?? 'https://cdn.jsdelivr.net/npm/bootstrap-4-grid@3.4.0/css/grid.min.css',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getLibraries(Editor $editor) {
    return [
      'core/jquery',
      'core/drupal',
      'core/drupal.ajax',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCssFiles(Editor $editor) {
    $settings = $editor->getSettings();
    $config = $settings['plugins']['flickr_formatter_bs_grid'] ?? [];
    return !empty($config['use_cdn']) && !empty($config['cdn_url']) ? [$config['cdn_url']] : [];
  }

}
