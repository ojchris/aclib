<?php

namespace Drupal\aclib_refdb\Form;

use Symfony\Component\DependencyInjection\ContainerInterface;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Routing\TrustedRedirectResponse;

use Drupal\node\NodeInterface;
use Drupal\aclib_refdb\AclibRefdbService;

/**
 * Provides a ACLIB Reference Database card form.
 */
class AclibRefDbCardForm extends FormBase {

  /**
   * AclibRefdbService definition.
   *
   * @var \Drupal\aclib_refdb\AclibRefdbService
   */
  protected $aclibService;

  /**
   * Constructor for AclibRefDbCardForm form.
   */
  public function __construct(AclibRefdbService $aclib_service) {
    $this->aclibService = $aclib_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('aclib_refdb.main')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'aclib_refdb_library_card_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $node = NULL) {

    // Define some required variables.
    $config = $this->aclibService->config->get('aclib_refdb.settings');
    $session = $this->aclibService->privateTempStore->get('aclib_refdb');
    $session_data = $session && is_array($session->get('aclib_refdb')) ? $session->get('aclib_refdb') : [];

    // Check on any necessary redirections.
    if ($redirect = $this->aclibService->redirection($node, $config, $session_data)) {
      return $redirect;
    }

    $form['aclib_refdb_helptext'] = [
      '#markup' => $config->get('aclib_refdb_cardformtext'),
    ];
    $form['aclib_refdb_nid'] = [
      '#value' => $node->id(),
      '#type' => 'hidden',
    ];
    $form['aclib_refdb_librarycardnumber'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Library Card Number'),
      '#size' => 20,
      '#maxlength' => 20,
      '#required' => TRUE,
      '#description' => $this->t('Enter your library card number.'),
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

    // Use these card numbers for testing: 22054001234567 | 33054001234567.
    $session = $this->aclibService->privateTempStore->get('aclib_refdb');
    $session_data = $session->get('aclib_refdb');

    $card_number = $form_state->getValue('aclib_refdb_librarycardnumber');
    // Remove any non-digit characters entered (usually spaces).
    $card_number = is_string($card_number) ? preg_replace("/[^0-9]/", "", $card_number) : $card_number;

    // Check to make sure $card_number matches pattern.
    $config_pattern_value = $this->aclibService->config->get('aclib_refdb.settings')->get('aclib_refdb_card_accept');
    $pattern_matched = $this->aclibService->multilineMatch($config_pattern_value, $card_number, TRUE);

    if (!$pattern_matched) {
      if (isset($session_data['aclib_refdb_cardtries'])) {
        $tryouts = $session_data['aclib_refdb_cardtries'] + 1;
        $session->set('aclib_refdb_cardtries', $tryouts);
      }
      else {
        $session->set('aclib_refdb_cardtries', 1);
      }
      $form_state->setErrorByName('aclib_refdb_librarycardnumber', $this->t('The library card number you entered is not valid.'));
    }
    else {
      $form_state->setValue('aclid_refdb_pattern_matched', $pattern_matched);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $node = $this->aclibService->entityTypeManager->getStorage('node')->load($form_state->getValue('aclib_refdb_nid'));

    // Redirect to external URL.
    if ($node instanceof NodeInterface) {

      $session = $this->aclibService->privateTempStore->get('aclib_refdb');

      // Set current session values.
      $session_update = [
        'cardVerified' => 1,
        'aclib_refdb_cardtries' => 0,
        'aclib_refdb_pattern' => $form_state->getValue('aclid_refdb_pattern_matched'),
      ];

      $session->set('aclib_refdb', $session_update);

      // Create instance of our custom logging entity.
      $data = [
        'nid' => $node->id(),
        'datetime' => $this->aclibService->prepareDate(),
        'location' => 'external',
        'remote_addr' => $this->aclibService->requestStack->getClientIp(),
        'pattern_matched' => $form_state->getValue('aclid_refdb_pattern_matched'),
      ];

      $this->aclibService->logAccess($data);
      $external_url = $this->aclibService::EXTERNAL_URL;

      if ($node->hasField($external_url) && !empty($node->get($external_url)->getValue())) {

        // Define our redirect.
        $url = $node->get($external_url)->getValue()[0]['uri'];
        $response = new TrustedRedirectResponse(Url::fromUri($url)->toString());

        // Take care of some cache here.
        $metadata = $response->getCacheableMetadata();
        $metadata->setCacheMaxAge(0);

        // Finally do a redirect.
        $form_state->setResponse($response);
      }
    }
  }

}
