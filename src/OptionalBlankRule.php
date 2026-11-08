<?php

declare(strict_types=1);

namespace SchemaForms;

use JsonSchema\Validator;

/**
 * Decides when an untouched optional control is left out of a submission.
 *
 * Form API submits an empty string for a control nobody touched. When the
 * property is optional and its schema rejects that blank, the property is
 * omitted, so validation reads it as absent instead of as an invalid value.
 * Each object consults its own required list.
 *
 * @internal
 */
final class OptionalBlankRule {

  /**
   * Creates the rule.
   *
   * @param \JsonSchema\Validator $validator
   *   Checks blanks against their property schema. Its factory resolves the
   *   references those schemas make, in its configured check mode.
   */
  public function __construct(
    private readonly Validator $validator,
  ) {}

  /**
   * Checks whether a refined property is omitted from its object.
   *
   * @param int|string $key
   *   The property name, as PHP keys it.
   * @param mixed $submitted
   *   The submitted value.
   * @param mixed $refined
   *   The value after type refinement.
   * @param object $schema
   *   The object schema.
   * @param object $property_schema
   *   The schema of the property.
   *
   * @return bool
   *   TRUE when the property is left out.
   */
  public function omits(int|string $key, mixed $submitted, mixed $refined, object $schema, object $property_schema): bool {
    // A blank refined to NULL stays: the property accepts NULL.
    return $submitted === '' && $refined === ''
      && !self::isRequired($key, $schema)
      && !$this->accepts($submitted, $property_schema);
  }

  /**
   * Checks whether the object schema lists a property as required.
   *
   * PHP turns a decimal property name such as "123" into an integer array key,
   * while the schema lists it as a string.
   *
   * @param int|string $key
   *   The property name, as PHP keys it.
   * @param object $schema
   *   The object schema.
   *
   * @return bool
   *   TRUE when the property is required.
   */
  private static function isRequired(int|string $key, object $schema): bool {
    $required = $schema->required ?? [];
    if (!is_array($required)) {
      return FALSE;
    }
    return in_array((string) $key, $required, TRUE) || in_array($key, $required, TRUE);
  }

  /**
   * Checks a value against a schema.
   *
   * @param mixed $value
   *   The value.
   * @param object $schema
   *   The schema.
   *
   * @return bool
   *   TRUE when the schema accepts the value.
   */
  private function accepts(mixed $value, object $schema): bool {
    $this->validator->reset();
    $this->validator->validate($value, $schema);
    return $this->validator->isValid();
  }

}
