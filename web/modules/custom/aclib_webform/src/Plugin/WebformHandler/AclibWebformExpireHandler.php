<?php

namespace Drupal\aclib_webform\Plugin\WebformHandler;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Datetime\DateHelper;
use Drupal\webform\WebformInterface;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformSubmissionInterface;

/**
 * Webform validate handler.
 *
 * @WebformHandler(
 *   id = "aclib_webform_expire_validator",
 *   label = @Translation("Limit submissions"),
 *   category = @Translation("Settings"),
 *   description = @Translation("Limit submissions by card number, to 5 per week."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 *   submission = \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_OPTIONAL,
 * )
 */
class AclibWebformExpireHandler extends WebformHandlerBase {

  use StringTranslationTrait;

  // Define maximum number of submissions per week.
  const LIMIT = 5;

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {
    if ($card_number = $form_state->getValue('library_card_number')) {
      $webform = $webform_submission->getWebform();
      if ($webform instanceof WebformInterface) {
        $results = $this->query($webform->id(), (int) $card_number);
        if ($results >= static::LIMIT) {
          $form_state->setErrorByName('library_card_number', $this->t('You reached your maximum of five titles per week.'));
          return;
        }
      }
    }
  }

  /**
   * Custom query on webform submission data.
   *
   * Query checks if the same library card number submitted
   * more than a number of times within a current week.
   *
   * @param int $webform_id
   *   ID opf the webform in question.
   * @param int $card_number
   *   Library card number field value.
   *
   * @return int
   *   A result of a count query.
   */
  protected function query($webform_id, $card_number) {

    $day_of_week = (int) DateHelper::dayOfWeek('now');

    $first_day = $day_of_week - 1;
    $first_day_string = '-' . $first_day . ' days';

    // It is "8" instead of "7" here because of the EST timezone
    // where we need one day after at 3h59m59s.
    $last_day = 8 - $day_of_week;
    $last_day_string = '+' . $last_day . ' days';

    // Timezone fix.
    $start_date = new DrupalDateTime($first_day_string);
    $start_date->setTime(4, 0, 0);

    // Timezone fix.
    $end_date = new DrupalDateTime($last_day_string);
    $end_date->setTime(03, 59, 59);

    $query = \Drupal::service('database')->select('webform_submission_data', 'd');
    $query->join('webform_submission', 's', '(d.sid= s.sid AND d.webform_id = s.webform_id AND s.created >= :start AND s.created <= :end)', [
      ':start' => $start_date->getTimestamp(),
      ':end' => $end_date->getTimestamp(),
    ]);

    // Return count result.
    return $query->fields('d', ['sid'])
      ->condition('d.name', 'library_card_number', '=')
      ->condition('d.value', $card_number, '=')
      ->groupBy('d.sid')
      ->distinct()
      ->countQuery()
      ->execute()
      ->fetchField();
  }

}
