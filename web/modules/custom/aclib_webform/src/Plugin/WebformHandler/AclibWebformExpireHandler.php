<?php

namespace Drupal\aclib_webform\Plugin\WebformHandler;

use Drupal\Core\Render\Element\RenderCallbackInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Datetime\DrupalDateTime;
// use Drupal\Core\Datetime\DateHelper;.
use Drupal\Component\Utility\Number as NumberUtility;

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
class AclibWebformExpireHandler extends WebformHandlerBase implements RenderCallbackInterface {

  use StringTranslationTrait;

  // Define maximum number of submissions per week.
  const LIMIT = 5;
  // Define constant error for library card field.
  const DEFAULT_ERROR = 'Please enter a valid library card number.';

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['libraryCardNumberPreRender'];
  }

  /**
   * {@inheritdoc}
   */
  public function alterForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {
    if (isset($form['elements']['library_card_number']) && isset($form['elements']['library_card_number']['#element_validate'])) {
      // Use our custom validtion instead of
      // default one for Number render element.
      $form['elements']['library_card_number']['#element_validate'] = [
        [static::class, 'validateLibraryCardNumber'],
      ];
      $form['elements']['library_card_number']['#pre_render'][] = [
        static::class, 'libraryCardNumberPreRender',
      ];
    }
  }

  /**
   * Custom pre_render callback for library card element.
   *
   * Replace attributes used by clientside_validation module.
   *
   * @param array $element
   *   Library card form element.
   *
   * @return array
   *   Library card form element.
   */
  public static function libraryCardNumberPreRender(array $element) {
    $element['#attributes']['data-msg-min'] = t('@default', ['@default' => static::DEFAULT_ERROR]);
    $element['#attributes']['data-msg-max'] = t('@default', ['@default' => static::DEFAULT_ERROR]);
    return $element;
  }

  /**
   * Custom validation method for library card number element.
   *
   * Basically we just change min and max validation strings.
   *
   * @param array $element
   *   Library card form element.
   * @param Drupal\Core\Form\FormStateInterface $form_state
   *   FormState object.
   * @param array $complete_form
   *   A whole webform array.
   *
   * @see \Drupal\Core\Render\Element\Number
   */
  public static function validateLibraryCardNumber(array &$element, FormStateInterface $form_state, array &$complete_form) {

    $value = $element['#value'];
    if ($value === '') {
      return;
    }

    $name = empty($element['#title']) ? $element['#parents'][0] : $element['#title'];

    // Ensure the input is numeric.
    if (!is_numeric($value)) {
      $form_state->setError($element, t('%name must be a number.', ['%name' => $name]));
      return;
    }

    // Ensure that the input is greater than the #min property, if set.
    if (isset($element['#min']) && $value < $element['#min']) {
      $form_state->setError($element, t('@default', ['@default' => static::DEFAULT_ERROR]));
    }

    // Ensure that the input is less than the #max property, if set.
    if (isset($element['#max']) && $value > $element['#max']) {
      $form_state->setError($element, t('@default', ['@default' => static::DEFAULT_ERROR]));
    }

    if (isset($element['#step']) && strtolower($element['#step']) != 'any') {
      // Check that the input is an allowed multiple of #step (offset by #min if
      // #min is set).
      $offset = $element['#min'] ?? 0.0;

      if (!NumberUtility::validStep($value, $element['#step'], $offset)) {
        $form_state->setError($element, t('%name is not a valid number.', ['%name' => $name]));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {

    if ($card_number = $form_state->getValue('library_card_number')) {
      $webform = $webform_submission->getWebform();
      if ($webform instanceof WebformInterface) {
        $results = $this->query($webform->id(), (int) $card_number);
        if ($results >= static::LIMIT) {
          $form_state->setErrorByName('library_card_number', $this->t('You reached your maximum of @num titles per 7-day period.', ['@num' => static::LIMIT]));
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

    /*
    // Old version with "current week" and timezone settings.
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
     */

    // Changed to 6 days - see OA5879
    //$start_date = new DrupalDateTime('-7 days');
    $start_date = new DrupalDateTime('-6 days');
    $end_date = new DrupalDateTime('now');

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
