<?php

declare(strict_types=1);

namespace SchemaForms;

use SchemaForms\Exception\UnencodableDataException;
use Shaper\Transformation\TransformationBase;
use Shaper\Util\Context;
use Shaper\Validator\AcceptValidator;
use Shaper\Validator\ValidateableInterface;

/**
 * Turns associative arrays into stdClass.
 *
 * The transform() method exists so a Shaper transformation pipeline can
 * include this step; nothing in the library calls it. Call convert() directly
 * for an exception on data with no JSON encoding.
 *
 * @internal
 */
final class ArrayToStdClass extends TransformationBase {

  /**
   * Converts data to the shape json_decode() gives it.
   *
   * Lists stay arrays; associative arrays and objects become stdClass, and an
   * object keeps only its public properties.
   *
   * @param mixed $data
   *   The data.
   *
   * @return mixed
   *   The converted data.
   *
   * @throws \SchemaForms\Exception\UnencodableDataException
   *   When the data has no JSON encoding.
   */
  public static function convert(mixed $data): mixed {
    try {
      return json_decode(json_encode($data, JSON_THROW_ON_ERROR), FALSE, 512, JSON_THROW_ON_ERROR);
    }
    catch (\JsonException $e) {
      throw new UnencodableDataException('The data cannot be encoded as JSON: ' . $e->getMessage(), 0, $e);
    }
  }

  /**
   * {@inheritdoc}
   *
   * Data with no JSON encoding becomes an empty stdClass. Call convert() to
   * get an exception instead.
   */
  #[\Override]
  protected function doTransform(mixed $data, Context $context): mixed {
    try {
      return self::convert($data);
    }
    catch (UnencodableDataException) {
      return new \stdClass();
    }
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function getInputValidator(): ValidateableInterface {
    return new AcceptValidator();
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function getOutputValidator(): ValidateableInterface {
    return new AcceptValidator();
  }

}
