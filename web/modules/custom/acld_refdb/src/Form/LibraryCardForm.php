<?php

namespace Drupal\acld_refdb\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;

/**
 * Provides a ACLD Reference Database form.
 */
class LibraryCardForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'acld_refdb_library_card_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $node = NULL) {
    // if the user has too many retries - send them away.
    // TODO: Figure out best way to do this in Drupal 8.
    //if (isset($_SESSION['acld_refdb_cardtries']) && ($_SESSION['acld_refdb_cardtries'] > 10)) {
    //  drupal_goto('node/'. \Drupal::config('acld_refdb.settings')->get('acld_refdb_failurenodeid'));
    //}

    $form['acld_refdb_helptext'] = [
      '#markup' => \Drupal::config('acld_refdb.settings')->get('acld_refdb_cardformtext'),
    ];
    $form['acld_refdb_nid'] = [
      '#value' => $node->id(),
      '#type' => 'hidden',
    ];
    $form['acld_refdb_librarycardnumber'] = [
      '#type' => 'textfield',
      '#title' => t('Library Card Number'),
      '#size' => 20,
      '#maxlength' => 20,
      '#required' => TRUE,
      '#description' => 'Enter your library card number.',
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => t('Submit')
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $card_number = $form_state->getValue('acld_refdb_librarycardnumber');
    //remove any non-digit characters entered (usually spaces).
    $card_number = preg_replace("/[^0-9]/", "", $card_number);


    //check to make sure $card_number matches pattern
    // TODO: get this custom function working.
    $pattern_matched = TRUE;
    //$pattern_matched = _acld_refdb_card_number_matches($card_number);


    if (!$pattern_matched) {
      if (isset($_SESSION['acld_refdb_cardtries'])) {
        $_SESSION['acld_refdb_cardtries'] += 1;
      }
      else {
        $_SESSION['acld_refdb_cardtries'] = 1;
      }
      $form_state->setErrorByName('acld_refdb_librarycardnumber', $this->t('The library card number you entered is not valid.'));
    }
    else {
      $form_state->setValue('aclid_refdb_pattern_matched', $pattern_matched);
    }

    //if (mb_strlen($form_state->getValue('message')) < 10) {
    //  $form_state->setErrorByName('name', $this->t('Message should be at least 10 characters.'));
    //}
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $node = \Drupal::entityTypeManager()->getStorage('node')->load($form_state->get('acld_refdb_nid'));
    $_SESSION['cardVerified'] = TRUE;
    $_SESSION['acld_refdb_cardtries'] = 0; // Reset the cardtries counter.
    $_SESSION['acld_refdb_pattern'] = $form_state['aclid_refdb_pattern_matched'];

    // TODO: take care of the logging stuff.
    //_acld_refdb_logaccess($node->nid, 1, $form_state['aclid_refdb_pattern_matched']);

    $form_state->setRedirect($node->field_external_url[\Drupal\Core\Language\Language::LANGCODE_NOT_SPECIFIED][0]['url']);

    //$this->messenger()->addStatus($this->t('The message has been sent.'));
    //$form_state->setRedirect('<front>');
  }

}
