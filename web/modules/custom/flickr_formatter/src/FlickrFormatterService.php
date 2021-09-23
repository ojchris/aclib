<?php

namespace Drupal\flickr_formatter;

use Symfony\Component\DependencyInjection\ContainerInterface;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

use Drupal\flickr_api\Service\Helpers;
use Drupal\flickr_api\Service\Photos;
use Drupal\flickr_api\Service\Photosets;
use Drupal\flickr_api\Service\Groups;
use Drupal\flickr_api\Service\Galleries;
use Drupal\flickr_api\Service\People;
use Drupal\flickr_api\Service\Tags;

/**
 * FlickrFormatterService class definition.
 */
class FlickrFormatterService {

  use StringTranslationTrait;

  /**
   * ConfigFactoryInterface definition.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  public $config;

  /**
   * MessengerInterface definition.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  public $messenger;

  /**
   * Module handler class.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  public $moduleHandler;

  /**
   * Drupal\Core\Entity\EntityTypeManager definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  public $settings;

  /**
   * Drupal\Core\Entity\EntityTypeManager definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  public $thirdPartySettings = [];


  /**
   * Object containing all of the Flickr API classes.
   *
   * @var object[]
   */
  public $flickrApi;

  /**
   * Class constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Configuration factory.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   Drupal Messanger interface.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   Module handler interface.
   * @param \Drupal\flickr_api\Service\Helpers $flickr_api_helpers
   *   Flicrk API Helpers class.
   * @param \Drupal\flickr_api\Service\Photos $flickr_api_photos
   *   Flicrk API Photos class.
   * @param \Drupal\flickr_api\Service\Photosets $flickr_api_photosets
   *   Flicrk API Photosets class.
   * @param \Drupal\flickr_api\Service\Groups $flickr_api_groups
   *   Flicrk API Groups class.
   * @param \Drupal\flickr_api\Service\Galleries $flickr_api_galleries
   *   Flicrk API Galleries class.
   * @param \Drupal\flickr_api\Service\People $flickr_api_people
   *   Flicrk API People class.
   * @param \Drupal\flickr_api\Service\Tags $flickr_api_tags
   *   Flicrk API Tags class.
   */
  public function __construct(ConfigFactoryInterface $config_factory, MessengerInterface $messenger, ModuleHandlerInterface $module_handler, Helpers $flickr_api_helpers, Photos $flickr_api_photos, Photosets $flickr_api_photosets, Groups $flickr_api_groups, Galleries $flickr_api_galleries, People $flickr_api_people, Tags $flickr_api_tags) {

    // Config object and our specific config settings.
    $this->config = $config_factory;
    $this->settings = $this->config->get('flickr_formatter.settings');

    // Messenger.
    $this->messenger = $messenger;

    // Module handler.
    $this->moduleHandler = $module_handler;

    // Check on any third party settings. This will be sub-modules most likely.
    $this->getThirdPartySettings();

    // Flickr API services, place all into a single object.
    $this->flickrApi = (object) [];
    $this->flickrApi->helpers = $flickr_api_helpers;
    $this->flickrApi->photos = $flickr_api_photos;
    $this->flickrApi->photosets = $flickr_api_photosets;
    $this->flickrApi->groups = $flickr_api_groups;
    $this->flickrApi->galleries = $flickr_api_galleries;
    $this->flickrApi->people = $flickr_api_people;
    $this->flickrApi->tags = $flickr_api_tags;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('messenger'),
      $container->get('module_handler'),
      $container->get('flickr_api.helpers'),
      $container->get('flickr_api.photos'),
      $container->get('flickr_api.photosets'),
      $container->get('flickr_api.groups'),
      $container->get('flickr_api.galleries'),
      $container->get('flickr_api.people'),
      $container->get('flickr_api.tags')
    );
  }

  /**
   * Scan sub-directory "modules" for "pluggable" modules.
   *
   * @param bool $active
   *   If FALSE there's no check if module is actually enabled.
   *
   * @return array
   *   Array with sub-modules names.
   */
  public function getSubmodules(bool $active = TRUE) {
    $active_submodules = [];
    $module_path = $this->moduleHandler->getModule('flickr_formatter')->getPath();
    $submodules = array_diff(scandir($module_path . '/modules'), ['..', '.']);
    if (is_array($submodules) && !empty($submodules)) {
      foreach (array_values($submodules) as $submodule) {
        if ($active) {
          if ($this->moduleHandler->moduleExists($submodule)) {
            $active_submodules[$submodule] = $submodule;
          }
        }
        else {
          $active_submodules[$submodule] = $submodule;
        }
      }
    }
    return $active_submodules;
  }

  /**
   * Get default options for configuration form elements.
   *
   * @return array
   *   Array with options ready for <select> or similar form elements.
   */
  public function getDefaultOptions() {

    $default_settings = $this->settings->getRawData();
    if (!empty($this->thirdPartySettings)) {
      foreach ($this->thirdPartySettings as $style_id => $style) {
        $default_settings['style'][$style_id] = isset($style['label']) ? $style['label'] : $style_id;
      }
    }
    $settings = [];
    foreach ($default_settings as $id => $value) {
      if (is_array($value)) {
        $settings[$id] = isset($value['label']) ? $value['label'] : $value;
      }
      else {
        $settings[$id] = $value;
      }
    }
    return $settings;
  }

  /**
   * Get available image sizes via Flick API method.
   *
   * @return array
   *   Associative array with image sizes/presets.
   */
  public function getSizesOptions() {
    $size_options = [];
    foreach ($this->flickrApi->helpers->photoSizes() as $size => $size_data) {
      $size_options[$size] = $size_data['label'];
    }
    return $size_options;
  }

  /**
   * Process default values.
   *
   * Loop through configuration settings values and fallback to defaults,
   * from settings.yml for the missing ones.
   *
   * @param array $defaults
   *   An array with default values, most likely from any configuration forms.
   *   If it's empty we pull those from module's settings.yml.
   *
   * @return array
   *   Associative array with a final default values.
   */
  public function getDefaultValues(array $defaults = []) {
    $default_settings = $this->settings->getRawData();
    $default_values = isset($defaults['#default_value']) && !empty($defaults['#default_value']) ? $defaults['#default_value'] : NULL;
    $values = [];

    if ($default_values) {
      foreach ($default_values as $id => $value) {
        if (!empty($value) && !is_null($value)) {
          $values[$id] = $value;
        }
        else {
          if (isset($default_settings[$id])) {
            if (is_array($default_settings[$id])) {
              $keys = !empty($default_settings[$id]) ? array_keys($default_settings[$id]) : NULL;
              $values[$id] = $keys ? reset($keys) : NULL;
            }
            else {
              $values[$id] = $default_settings[$id];
            }
          }
          else {
            $values[$id] = $value;
          }
        }
      }
      return $values;
    }
    else {
      return $this->parseSettings($default_settings);
    }
  }

  /**
   * Process single image (Flickr type: photo configuration setting).
   *
   * @param array $photo
   *   Associative array with photo data, as returned from API.
   * @param string $size
   *   Image size preset, returned from config.
   * @param mixed|string $title
   *   Image title and alt attributes values.
   *
   * @return array
   *   Render array ready for "flickr_formatter_image" theme.
   */
  public function processSingle(array $photo, string $size, $title = NULL) {

    if (!isset($photo['id']) || empty($photo['id'])) {
      return [];
    }

    try {
      $photo = $this->flickrApi->photos->photosGetInfo($photo['id']);
      return $this->processImage($photo, $size, $title);
    }
    catch (\Throwable $e) {
      $this->messenger->addError('WRONG Flick ID: ' . $e->getMessage());
    }
  }

  /**
   * Process group of Flickr images (photosets, galleries, people etc.).
   *
   * @param array $photo
   *   Associative array with photo data, as returned from API.
   * @param array $settings
   *   Provided default YML settings for this formatter/theme/configuration.
   * @param array $third_party_settings
   *   Third party settings, most likely implemented via sub-modules.
   *
   * @return array
   *   Render array ready for "flick_formatter_$plugin" theme.
   */
  public function processGroup(array $photo, array $settings, array $third_party_settings = []) {

    if (!isset($photo['id']) || empty($photo['id'])) {
      return [];
    }

    $size = isset($settings['size']) && !empty($settings['size']) ? $settings['size'] : '-';
    $caption = isset($settings['caption']) && !empty($settings['caption']) ? $settings['caption'] : 0;
    $base_remote_uri = $this->config->get('flickr_api.settings')->get('host_uri') . '/photos/';

    $elements = [
      '#theme' => 'flickr_formatter_' . $settings['style'],
      '#images' => [],
      '#options' => $settings + $third_party_settings,
      '#attributes' => [],
    ];

    $params = [
      'per_page' => $settings['per_page'],
      'media' => 'photos',
    ];

    $photosets = $this->flickrApi->photosets->photosetsGetPhotos((int) $photo['id'], $params, 1);
    if (is_array($photosets) && isset($photosets['photo'])) {
      if (!empty($photosets['photo'])) {
        $max_width = NULL;
        foreach ($photosets['photo'] as $index => $photo) {

          $elements['#images'][$index]['image'] = $this->processImage($photo, $size);

          if ($settings['max_width'] && $index == 0 && isset($elements['#images'][$index]['image']['#width'])) {
            $max_width = $elements['#images'][$index]['image']['#width'];
          }

          $elements['#images'][$index]['remote_url'] = $base_remote_uri . $photo['pathalias'] . '/' . $photo['id'];
          if ($caption > 0) {
            $elements['#images'][$index]['caption'] = $this->processImageCaption($photo);
          }
        }
        if ($max_width) {
          $elements['#options']['max_width'] = $max_width;
        }
      }
    }
    return $elements;
  }

  /**
   * Wrap Flickr image into default Drupal's image theme.
   *
   * @param array $photo
   *   Associative array with photo data, as returned from API.
   * @param string $size
   *   Image size preset, returned from config.
   * @param mixed|string $title
   *   Image title and alt attributes values.
   *
   * @return array
   *   Associative array with prepared attributes for image element.
   */
  public function processImage(array $photo, string $size = 'm', $title = NULL) {

    if (!$title) {
      if (isset($photo['title'])) {
        $title = $this->t('@title', ['@title' => $photo['title']]);
      }
      else {
        $title = isset($photo['ownername']) ? $this->t('Photo by @ownername', [
          '@ownername' => $photo['ownername'],
        ]) : $this->t('Flickr photo: @id', [
          '@id' => $photo['id'],
        ]);
      }
    }

    // This is a performance bottleneck!
    // Not worth for a small gain on html standards subject.
    // $attributes = $this->processImageAttributes($photo, $size);

    return [
      '#theme' => 'image',
      '#uri' => $this->flickrApi->helpers->photoImgUrl($photo, $size),
      '#alt' => $title,
      '#title' => $title,
      //'#width' => isset($attributes['width']) ? $attributes['width'] : NULL,
      //'#height' => isset($attributes['height']) ? $attributes['height'] : NULL,
      '#attributes' => [
        //'data-aspect' => isset($attributes['data-aspect']) ? $attributes['data-aspect'] : 'square',
        'class' => ['img-fluid'],
      ],
    ];
  }

  /**
   * Process image attributes.
   *
   * @param array $photo
   *   Associative array with photo data, as returned from API.
   * @param string $size
   *   Image size preset, returned from config.
   *
   * @return array
   *   Associative array with prepared attributes for image element.
   */
  public function processImageAttributes(array $photo, string $size) {

    $attributes = [];
    if ($current_photo_sizes = $this->flickrApi->photos->photosGetSizes($photo['id'])) {
      $all_sizes = $this->getSizesOptions();
      $label = isset($all_sizes[$size]) ? $all_sizes[$size] : 'm';

      foreach ($current_photo_sizes as $current_photo_size) {
        if ($current_photo_size['label'] == $label) {
          if (isset($current_photo_size['width'])) {
            $attributes['width'] = (int) $current_photo_size['width'];
          }
          if (isset($current_photo_size['height'])) {
            $attributes['height'] = (int) $current_photo_size['height'];

            if (isset($attributes['width'])) {
              $attributes['data-aspect'] = $this->processImageAspectRatio($attributes['width'], $attributes['height']);
            }
          }
        }
      }
    }
    return $attributes;
  }

  /**
   * Process image caption.
   *
   * @param array $photo
   *   Associative array with photo data, as returned from API.
   *
   * @return array
   *   Render array for "flickr_formatter_image_caption" theme.
   *   @see templates/flickr-formatter-image-caption.html.twig
   */
  public function processImageCaption(array $photo) {
    return [
      '#theme' => 'flickr_formatter_image_caption',
      '#caption' => [
        'title' => $photo['title'],
        'owner' => $photo['ownername'],
        'date' => $photo['dateupload'],
      ],
    ];
  }

  /**
   * Figure image aspect ratio.
   *
   * @param int $width
   *   Image width value.
   * @param int $height
   *   Image height value.
   *
   * @return string
   *   Aspect ratio, value as "square", "landscape" or "portrait".
   */
  public function processImageAspectRatio(int $width, int $height) {

    $settings = array_keys($this->settings->get('aspect'));
    $aspect_ratio = $width / $height;

    // "Square" image
    if ($aspect_ratio == 1) {
      return $settings[0];
    }
    // "Portrait" image
    elseif ($aspect_ratio < 1) {
      return $settings[1];
    }
    // "Landscape" image
    else {
      return $settings[2];
    }
  }

  /**
   * Consider sub-modules that may implement third-party settings.
   */
  public function getThirdPartySettings() {
    if (!empty($this->getSubmodules()) && empty($this->thirdPartySettings)) {
      foreach ($this->getSubmodules() as $submodule) {
        if ($submodule_settings = $this->config->get($submodule . '.settings')) {
          $submodule_data = $submodule_settings->getRawData();
          if (isset($submodule_data['_core'])) {
            unset($submodule_data['_core']);
          }
          $this->thirdPartySettings += $submodule_data;
        }
      }
    }
  }

  /**
   * Parse this module's settings (YML) file.
   *
   * @param array $default_settings
   *   Associative array with settings provided by caller.
   *
   * @return array
   *   Associative array with cleaned and keyed settings.
   */
  protected function parseSettings(array $default_settings = []) {
    $default_settings = empty($default_settings) ? $this->settings->getRawData() : $default_settings;
    $settings = [];
    foreach ($default_settings as $id => $value) {
      if (is_array($value)) {
        $keys = array_keys($value);
        $settings[$id] = reset($keys);
        foreach ($value as $key => $item) {
          if (is_array($item) && !empty($item)) {
            $settings[$key] = isset($item['options']) ? $item['options'] : [];
          }
        }
      }
      else {
        $settings[$id] = $value;
      }
    }
    return $settings;
  }

}
