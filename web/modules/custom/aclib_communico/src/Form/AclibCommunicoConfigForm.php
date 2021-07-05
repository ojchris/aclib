<?php

/**
 * @file
 * Contains \Drupal\aclib_communico\Form\AclibCommunicoConfigForm.
 */

namespace Drupal\aclib_communico\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class AclibCommunicoConfigForm extends ConfigFormBase {

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

    $config = $this->config('aclib_communico.settings')->getRawData();

    $form['communico_api'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Communico API credentials'),
      '#tree' => TRUE,
    ];

    $form['communico_api']['access_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Access Key'),
      '#default_value' => $config['access_key'],
      '#required' => TRUE,
    ];

    $form['communico_api']['secret_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Secret Key'),
      '#default_value' => $config['secret_key'],
      '#required' => TRUE,
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
      '#default_value' => $config['guzzle_options']['base_uri'],
      '#required' => TRUE,
    ];
  
    $form['guzzle_options']['timeout'] = [
      '#type' => 'number',
      '#title' => $this->t('Timeout'),
      '#default_value' => $config['guzzle_options']['timeout'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $config = $this->config('aclib_communico.settings');
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
          $config->set($v_key, $v);
        }
      }
    }
    $config->save();

    return parent::submitForm($form, $form_state);
  }
}