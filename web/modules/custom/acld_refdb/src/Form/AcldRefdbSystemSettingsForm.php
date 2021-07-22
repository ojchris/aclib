<?php

/**
 * @file
 * Contains \Drupal\acld_refdb\Form\AcldRefdbSystemSettingsForm.
 */

namespace Drupal\acld_refdb\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;

class AcldRefdbSystemSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'acld_refdb_system_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('acld_refdb.settings');

    foreach (Element::children($form) as $variable) {
      $config->set($variable, $form_state->getValue($form[$variable]['#parents']));
    }
    $config->save();

//    if (method_exists($this, '_submitForm')) {
//      $this->_submitForm($form, $form_state);
//    }

    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['acld_refdb.settings'];
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = \Drupal::config('acld_refdb.settings');

    $form['acld_refdb_internalips'] = [
      '#type' => 'textarea',
      '#title' => t('ACLD Internal IP Addresses'),
      '#default_value' => $config->get('acld_refdb_internalips'),
      '#required' => TRUE,
      '#rows' => 3,
      '#description' => 'Enter all (including headquarters) internal IP addresses for ACLD. You may use wildcards (192.168.1.*) Please enter one per line',
    ];
    $form['acld_refdb_hqips'] = [
      '#type' => 'textarea',
      '#title' => t('ACLD Headquarters IP Addresses'),
      '#default_value' => $config->get('acld_refdb_hqips'),
      '#required' => TRUE,
      '#rows' => 3,
      '#description' => 'Enter only the headquarters IP addresses for ACLD. You may use wildcards (192.168.1.*) Please enter one per line',
    ];
    $form['acld_refdb_failurenodeid'] = [
      '#type' => 'textfield',
      '#title' => t('Failure Node ID'),
      '#default_value' => $config->get('acld_refdb_failurenodeid'),
      '#size' => 10,
      '#maxlength' => 10,
      '#required' => TRUE,
      '#description' => 'Enter the node ID that the user should be sent to if they fail to enter a correct library card number 3 times in a row.',
    ];
    $form['acld_refdb_cardformtext'] = [
      '#type' => 'textfield',
      '#title' => t('Database Access Form Help Text'),
      '#default_value' => $config->get('acld_refdb_cardformtext'),
      '#size' => 100,
      '#maxlength' => 200,
      '#required' => TRUE,
      '#description' => 'Enter the help text that explains entering the Library Card Number.',
    ];
    $form['acld_refdb_card_accept'] = [
      '#type' => 'textfield',
      '#title' => t('Library Card Number Accept Pattern'),
      '#default_value' => $config->get('acld_refdb_card_accept'),
      '#size' => 20,
      '#maxlength' => 20,
      '#required' => TRUE,
      '#description' => 'Enter the pattern that users can use for their Library Card Number.  For all spaces that can be any character put a "*"',
    ];
    $form['acld_refdb_card_accept_alternate'] = [
      '#type' => 'textfield',
      '#title' => t('Library Card Number Accept Pattern (alternate)'),
      '#default_value' => $config->get('acld_refdb_card_accept_alternate'),
      '#size' => 20,
      '#maxlength' => 20,
      '#required' => TRUE,
      '#description' => 'Enter an alternate pattern that users can use for their Library Card Number.  For all spaces that can be any character put a "*"',
    ];
    return parent::buildForm($form, $form_state);
  }

}
