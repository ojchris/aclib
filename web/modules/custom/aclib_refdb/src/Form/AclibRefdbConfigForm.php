<?php

namespace Drupal\aclib_refdb\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 *
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
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $config = $this->config('aclib_refdb.settings');
    $config_keys = array_keys($config->getRawData());

    foreach ($form_state->getValues() as $value_key => $value) {
      // We do want only our keys/values that are defined in config
      // Else other form properties such as "form_id" and "form_build_id" get saved too.
      if (in_array($value_key, $config_keys) && !empty($value)) {
        $config->set($value_key, $value);
      }
    }
    $config->save();

    parent::submitForm($form, $form_state);
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
      '#type' => 'textfield',
      '#title' => $this->t('Library Card Number Accept Pattern'),
      '#default_value' => $config->get('aclib_refdb_card_accept'),
      '#size' => 20,
      '#maxlength' => 20,
      '#required' => TRUE,
      '#description' => $this->t('Enter the pattern that users can use for their Library Card Number.  For all spaces that can be any character put a "*"'),
    ];
    $form['aclib_refdb_card_accept_alternate'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Library Card Number Accept Pattern (alternate)'),
      '#default_value' => $config->get('aclib_refdb_card_accept_alternate'),
      '#size' => 20,
      '#maxlength' => 20,
      '#required' => TRUE,
      '#description' => $this->t('Enter an alternate pattern that users can use for their Library Card Number.  For all spaces that can be any character put a "*"'),
    ];
    return parent::buildForm($form, $form_state);
  }

}
