<?php

namespace SchemaForms;

use Shaper\Validator\ValidateableBase;

/**
 * Checks whether the passed data is a Drupal render array.
 *
 * @internal
 */
class RenderArrayValidator extends ValidateableBase {

  /**
   * {@inheritdoc}
   */
  public function isValid($data) {
    if (!is_array($data)) {
      return FALSE;
    }
    // A string value is accepted when its key contains '#' anywhere.
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
