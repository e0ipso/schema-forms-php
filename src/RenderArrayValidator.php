<?php

namespace SchemaForms;

use Shaper\Validator\ValidateableBase;

/**
 * Checks weather or not the passed data is a Drupal render array.
 */
class RenderArrayValidator extends ValidateableBase {

  /**
   * {@inheritdoc}
   */
  public function isValid($data) {
    if (!is_array($data)) {
      return FALSE;
    }
    // There is at least one key that has a leading '#'.
    foreach ($data as $key => $val) {
      if (is_string($val)) {
        if (strpos($key, '#') !== FALSE) {
          return TRUE;
        }
        continue;
      }
      if (strpos($key, '#') !== FALSE) {
        continue;
      }
      return $this->isValid($val);
    }
    return FALSE;
  }

}
