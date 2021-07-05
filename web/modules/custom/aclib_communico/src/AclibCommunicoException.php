<?php

namespace Drupal\aclib_communico;

use \Exception;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Serialization\PhpSerialize;

/**
 * Custom Communico API exception.
 *
 */
class AclibCommunicoException extends Exception {

  /**
   * @inheritdoc
   */
  public function __construct($message = "", $code = 0, Exception $previous = NULL) {
    // Construct message from JSON if required.
    if (substr($message, 0, 1) == '{') {
      $message_obj = Json::decode($message);
      $message = $message_obj->status . ': ' . $message_obj->title . ' - ' . $message_obj->detail;
      if (!empty($message_obj->errors)) {
        $message .= ' ' . PhpSerialize::encode($message_obj->errors);
      }
    }
    parent::__construct($message, $code, $previous);
  }
}