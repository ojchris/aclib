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
 * Provides a ACLIB Reference Database form.
 */
class AclibRefDbCardForm extends FormBase {

  /**
   * AclibRefdbService definition.
   *
   * @var \Drupal\aclib_refdb\AclibRefdbService;
   */
  protected $aclib_service;

  /**
   * Constructor for AclibRefDbCardForm form
  */
  public function __construct(AclibRefdbService $aclib_service) {
    $this->aclib_service = $aclib_service;
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
    $config = $this->aclib_service->config->get('aclib_refdb.settings');
    $session = $this->aclib_service->privateTempStore->get('aclib_refdb');
    $session_data = $session && is_array($session->get('aclib_refdb')) ? $session->get('aclib_refdb') : [];
    // Check on any necessary redirections
    if ($redirect = $this->aclib_service->redirection($node, $config, $session_data)) {
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
      '#value' => $this->t('Submit')
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // 22054001234567
    // 33054001234567
    $session = $this->aclib_service->privateTempStore->get('aclib_refdb');
    $session_data = $session->get('aclib_refdb');

    $card_number = $form_state->getValue('aclib_refdb_librarycardnumber');
    // Remove any non-digit characters entered (usually spaces).
    $card_number = is_string($card_number) ? preg_replace("/[^0-9]/", "", $card_number) : $card_number;

    // Check to make sure $card_number matches pattern
    $pattern_matched = $this->aclib_service->cardMemberMatch($card_number, 1) ? $this->aclib_service->cardMemberMatch($card_number, 1) : $this->aclib_service->cardMemberMatch($card_number, 2, 'aclib_refdb_card_accept_alternate');

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

    $node = \Drupal::entityTypeManager()->getStorage('node')->load($form_state->getValue('aclib_refdb_nid'));

    // Redirect to external URL
    if ($node instanceof NodeInterface) {
      $session = $this->aclib_service->privateTempStore->get('aclib_refdb');
      // Set current state values
      $session_update = [
        'cardVerified' => 1,
        'aclib_refdb_cardtries' => 0,
        'aclib_refdb_pattern' => $form_state->getValue('aclid_refdb_pattern_matched'),
      ];

      $session->set('aclib_refdb', $session_update);

      // Create instance of our custom logging entity
      $data = [
        'nid' => $node->id(),
        'datetime' => $this->aclib_service->prepareDate(),
        'location' => 1,
        'remote_addr' => $this->aclib_service->requestStack->getClientIp(),
        'pattern_matched' => $form_state->getValue('aclid_refdb_pattern_matched'),
      ];

      $this->aclib_service->logAccess($data);
      $external_url =  $this->aclib_service::EXTERNAL_URL;

      if ($node->hasField($external_url) && !empty($node->get($external_url)->getValue())) {
        // Define our redirect
        $url = $node->get($external_url)->getValue()[0]['uri'];
        $response = new TrustedRedirectResponse(Url::fromUri($url)->toString());

        // Take care of some cache here
        $metadata = $response->getCacheableMetadata();
        $metadata->setCacheMaxAge(0);

        $form_state->setResponse($response);
      }
    }
  }
}
