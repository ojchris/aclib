<?php

namespace Drupal\acld_refdb\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Url;

/**
 * Default controller for the acld_refdb module.
 */
class DefaultController extends ControllerBase {

  /**
   *
   */
  public function acld_refdb_page(NodeInterface $node = NULL) {
    $config = \Drupal::config('acld_refdb.settings');
    $output = '';

    $front_page = Url::fromRoute('<front>')->toString();
    // If (is_null($node)) {
    //      return new RedirectResponse($front_page);
    //    }
    // Check to make sure the nid passed in belongs to a node of type 'referencedb'.
    if ($node->bundle() != 'referencedb') {
      return new RedirectResponse($front_page);
    }

    $user_ip = trim(\Drupal::request()->getClientIp());
    // $user_ip = '192.42.92.110';
    // $user_ip = '192.42.92.98';
    // $user_ip = '192.42.92.229';
    // Determine if the user is internal or external to the ACLD network.
    $hq_only_field_value = $node->get('field_hq_only')->getValue();
    $is_user_on_site = $this->acld_refdb_location($config->get('acld_refdb_internalips'), $config->get('acld_refdb_hqips'), $user_ip, $hq_only_field_value[0]['value']);

    // This is temp code - remove!
    $output = \Drupal::formBuilder()->getForm('Drupal\acld_refdb\Form\LibraryCardForm', $node);
    /*

    // send off-site users to the external URL for HQ-only DBs
    // see http://groups.drupaleasy.com/aclib/node/1049#comment-5540
    if ((!$on_site) && ($node->field_hq_only[\Drupal\Core\Language\Language::LANGCODE_NOT_SPECIFIED][0]['value'])) {
    drupal_goto($node->field_external_url[\Drupal\Core\Language\Language::LANGCODE_NOT_SPECIFIED][0]['url']);
    }

    // Determine if the DB requires sign on and handle appropriately
    if ($node->field_require_signon[\Drupal\Core\Language\Language::LANGCODE_NOT_SPECIFIED][0]['value'] != '1') {
    if ($on_site) {
    _acld_refdb_logaccess($node->nid, 0);
    drupal_goto($node->field_internal_url[\Drupal\Core\Language\Language::LANGCODE_NOT_SPECIFIED][0]['url']);
    }
    else {
    _acld_refdb_logaccess($node->nid, 1);
    drupal_goto($node->field_external_url[\Drupal\Core\Language\Language::LANGCODE_NOT_SPECIFIED][0]['url']);
    }
    }

    // If the user is on_site, send them on their way
    if ($on_site) {
    _acld_refdb_logaccess($node->nid, 0);
    drupal_goto($node->field_internal_url[\Drupal\Core\Language\Language::LANGCODE_NOT_SPECIFIED][0]['url']);
    }

    //unset($_SESSION['cardVerified']);
    // see if they have already been verified previously
    $card_verified = isset($_SESSION['cardVerified']) ? $_SESSION['cardVerified'] : NULL;
    // Send them to the external url if they've logged in before and their cookie is still valid,
    //   otherwise give them the library card form.
    if ($card_verified) {
    _acld_refdb_logaccess($node->nid, 1);
    drupal_goto($node->field_external_url[\Drupal\Core\Language\Language::LANGCODE_NOT_SPECIFIED][0]['url']);
    }
    else {
    $output = \Drupal::formBuilder()->getForm('acld_refdb_librarycardform', $nid, $on_site);
    }
     */

    return $output;
  }

  /**
   * Returns array depending on if the user is on-site or not.
   *
   * @param string $acld_ips
   *   IP address range for ACLD branches.
   * @param string $acld_hqips
   *   IP address range for ACLD headquarters branch.
   * @param string $user_ip
   *   The current user's IP address.
   * @param bool $hq_only
   *   True if reference database is only available from ACLD HQ.
   *
   * @return bool
   */
  protected function acld_refdb_location($acld_ips, $acld_hqips, $user_ip, $hq_only = FALSE) {
    // on-site is true, else false.
    if ($hq_only) {
      return $this->acld_refdb_checkips($acld_hqips, $user_ip);
    }
    return $this->acld_refdb_checkips($acld_ips, $user_ip);
  }

  /**
   * Checks to see if the user IP address is allowed to view database.
   *
   * @param string $allowed_ips
   *   Allowed IP addresses for a particular reference database.
   * @param string $user_ip
   *   The current user's IP address.
   *
   * @return bool
   */
  protected function acld_refdb_checkips($allowed_ips, $user_ip) {
    $ip_lines = preg_split('/\r\n|[\r\n]/', $allowed_ips);
    $match_base = '';
    foreach ($ip_lines as $ip_value) {
      if (!empty($ip_value)) {
        $subnet_pos = strpos($ip_value, "*");
        if ($subnet_pos) {
          $match_base = mb_substr($ip_value, 0, $subnet_pos);
        }
        else {
          $match_base = $ip_value;
        }
      }

      if (mb_substr($user_ip, 0, mb_strlen($match_base)) == trim($match_base)) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
