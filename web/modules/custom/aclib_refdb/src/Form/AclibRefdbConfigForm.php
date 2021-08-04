<?php

namespace Drupal\aclib_refdb\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a ACLIB Reference Database configuration form.
 */
class AclibRefdbConfigForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'aclib_refdb_config_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['aclib_refdb.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $config = $this->config('aclib_refdb.settings');

    $form['aclib_refdb_internalips'] = [
      '#type' => 'textarea',
      '#title' => $this->t('ACLIB Internal IP Addresses'),
      '#default_value' => $config->get('aclib_refdb_internalips'),
      '#required' => TRUE,
      '#rows' => 3,
      '#description' => $this->t('Enter all (including headquarters) internal IP addresses for ACLIB. You may use wildcards (192.168.1.*) Please enter one per line'),
    ];
    $form['aclib_refdb_hqips'] = [
      '#type' => 'textarea',
      '#title' => $this->t('ACLIB Headquarters IP Addresses'),
      '#default_value' => $config->get('aclib_refdb_hqips'),
      '#required' => TRUE,
      '#rows' => 3,
      '#description' => $this->t('Enter only the headquarters IP addresses for ACLIB. You may use wildcards (192.168.1.*) Please enter one per line'),
    ];
    $form['aclib_refdb_failurenodeid'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Failure Node ID'),
      '#default_value' => $config->get('aclib_refdb_failurenodeid'),
      '#size' => 10,
      '#maxlength' => 10,
      '#required' => TRUE,
      '#description' => $this->t('Enter the node ID that the user should be sent to if they fail to enter a correct library card number 3 times in a row.'),
    ];
    $form['aclib_refdb_cardformtext'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Database Access Form Help Text'),
      '#default_value' => $config->get('aclib_refdb_cardformtext'),
      '#size' => 100,
      '#maxlength' => 200,
      '#required' => TRUE,
      '#description' => $this->t('Enter the help text that explains entering the Library Card Number.'),
    ];

    $form['aclib_refdb_card_accept'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Library Card Number Accept Patterns'),
      '#default_value' => $config->get('aclib_refdb_card_accept'),
      '#required' => TRUE,
      '#rows' => 3,
      '#description' => $this->t('Enter the pattern that users can use for their Library Card Number. For all spaces that can be any character put a "*" and please enter one per line'),
    ];

    $form['aclib_refdb_debug'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Debug'),
      '#default_value' => $config->get('aclib_refdb_debug'),
      '#description' => $this->t('When enabled first a current user session is cleared (on submit). Then provided testing IP address is used.'),
      '#id' => 'aclib-refdb-debug',
    ];

    $form['aclib_refdb_debug_ip'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Debug IP Address'),
      '#default_value' => $config->get('aclib_refdb_debug_ip'),
      '#size' => 20,
      '#maxlength' => 100,
      '#description' => $this->t('Set the address here to use a fixed, for example useful when we want to be in the range of IPs provided above.'),
      '#states' => [
        'visible' => [
          ':input[id="aclib-refdb-debug"]' => ['checked' => TRUE],
        ],
      ],
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $config = $this->config('aclib_refdb.settings');
    $config_keys = array_keys($config->getRawData());

    foreach ($form_state->getValues() as $value_key => $value) {
      // We do want only our keys/values that are defined in config
      // Else form properties such as "form_id", "form_build_id" get saved too.
      if (in_array($value_key, $config_keys)) {
        $config->set($value_key, $value);
      }
    }
    $config->save();

    // If Debug is checked we clear previous session,
    // to start testing all over.
    if ($form_state->getValue('aclib_refdb_debug')) {
      \Drupal::service('aclib_refdb.main')->sessionDelete();
    }

    parent::submitForm($form, $form_state);
  }

}
