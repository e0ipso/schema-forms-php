<?php

namespace SchemaForms;

use Shaper\Transformation\TransformationBase;
use Shaper\Util\Context;
use Shaper\Validator\AcceptValidator;
use Shaper\Validator\ValidateableInterface;

/**
 * Turns associative arrays into stdClass.
 */
final class ArrayToStdClass extends TransformationBase {

  /**
   * {@inheritdoc}
   */
  protected function doTransform($data, Context $context) {
    try {
      $encoded = json_encode($data, JSON_THROW_ON_ERROR);
      return json_decode($encoded, FALSE, 512, JSON_THROW_ON_ERROR);
    }
    catch (\JsonException $e) {
      // Intentionally left blank.
    }
    return new \stdClass();
  }

  /**
   * {@inheritdoc}
   */
  public function getInputValidator(): ValidateableInterface {
    return new AcceptValidator();
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputValidator(): ValidateableInterface {
    return new AcceptValidator();
  }

}
