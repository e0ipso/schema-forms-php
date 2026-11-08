<?php

namespace SchemaForms;

use JsonSchema\Constraints\Factory;
use JsonSchema\Validator;

/**
 * Casts a data structure to its JSON-Schema the best it can.
 *
 * @internal
 */
final class RecursiveTypeCaster {

  /**
   * Takes a data structure and tries its best to fit the types in the schema.
   *
   * @param mixed $data
   *   The data.
   * @param object $schema
   *   The schema. Without a factory, a copy of it is registered with
   *   SchemaReferenceResolver::createFactory() and that copy is walked.
   * @param \JsonSchema\Constraints\Factory|null $factory
   *   A factory whose storage resolves every reference in $schema from any of
   *   its subschemas, such as one from SchemaReferenceResolver::createFactory()
   *   passed with SchemaReferenceResolver::registeredSchema() as $schema.
   *   Optional blanks are validated with it, in its configured check mode.
   *
   * @return mixed
   *   The same data with refined types.
   *
   * @throws \SchemaForms\Exception\UnsupportedReferenceException
   *   When the schema references a document outside itself.
   * @throws \JsonSchema\Exception\ExceptionInterface
   *   When a reference inside the schema cannot be resolved.
   */
  public static function recursiveTypeRefinements(mixed $data, object $schema, ?Factory $factory = NULL): mixed {
    if ($factory === NULL) {
      $factory = SchemaReferenceResolver::createFactory($schema);
      // Walk the registered copy: its references resolve from any subschema.
      $schema = SchemaReferenceResolver::registeredSchema($factory);
    }
    return self::refineValue($data, $schema, new Validator($factory));
  }

  /**
   * Refines a value against the schema that describes it.
   *
   * @param mixed $data
   *   The data.
   * @param object $schema
   *   The schema for this value.
   * @param \JsonSchema\Validator $validator
   *   Validates optional blanks; its factory resolves the root's references.
   *
   * @return mixed
   *   The same data with refined types.
   */
  private static function refineValue(mixed $data, object $schema, Validator $validator): mixed {
    $enum = $schema->enum ?? NULL;
    if (!isset($schema->type) && !empty($enum) && (is_string($data) || is_int($data))) {
      return self::matchEnumMember($data, $enum);
    }
    $types = self::types($schema);
    if (is_array($data) && array_is_list($data) && in_array('array', $types, TRUE)) {
      return self::refineItems($data, $schema, $validator);
    }
    if ((is_array($data) || is_object($data)) && in_array('object', $types, TRUE)) {
      return self::refineProperties($data, $schema, $validator);
    }
    return self::castScalar($data, $types, $schema);
  }

  /**
   * Returns the enum member a submitted option key stands for.
   *
   * @param string|int $data
   *   The submitted option key.
   * @param mixed $enum
   *   The enum keyword of an untyped schema.
   *
   * @return mixed
   *   The matching member, or the data when no member matches.
   */
  private static function matchEnumMember(string|int $data, mixed $enum): mixed {
    $members = [];
    foreach (is_array($enum) || is_object($enum) ? $enum : [] as $member) {
      if (!is_scalar($member) && $member !== NULL) {
        continue;
      }
      // Match rendering keys; the last member wins when keys collide.
      $key = is_float($member) ? (string) $member : ($member ?? '');
      $members[$key] = $member;
    }
    return array_key_exists($data, $members) ? $members[$data] : $data;
  }

  /**
   * Lists the types a schema declares.
   *
   * @param object $schema
   *   The schema.
   *
   * @return array
   *   The declared types; empty when the schema declares none.
   */
  private static function types(object $schema): array {
    $types = $schema->type ?? [];
    return is_array($types) ? $types : [$types];
  }

  /**
   * Refines each item of a list with the item schema.
   *
   * @param array $data
   *   The list.
   * @param object $schema
   *   The array schema.
   * @param \JsonSchema\Validator $validator
   *   Validates optional blanks.
   *
   * @return array
   *   The refined list.
   */
  private static function refineItems(array $data, object $schema, Validator $validator): array {
    $items = $schema->items ?? NULL;
    // Missing or list-form items give no single item schema to cast against.
    if (!is_object($items)) {
      return $data;
    }
    return array_map(static fn(mixed $item): mixed => self::refineValue($item, $items, $validator), $data);
  }

  /**
   * Refines each property and omits optional blanks the schema rejects.
   *
   * A stdClass is changed in place, so it stays an object at every level.
   *
   * @param array|object $data
   *   The object data.
   * @param object $schema
   *   The object schema.
   * @param \JsonSchema\Validator $validator
   *   Validates optional blanks.
   *
   * @return array|object
   *   The refined data.
   */
  private static function refineProperties(array|object $data, object $schema, Validator $validator): array|object {
    foreach ((array) $data as $key => $value) {
      $property_schema = $schema->properties->{$key} ?? $schema->items ?? (object) ['type' => 'null'];
      $refined = self::refineValue($value, $property_schema, $validator);
      // Nullable refinements take precedence over omitting untouched fields.
      $omit = $value === '' && $refined === ''
        && !self::isRequired($key, $schema)
        && !self::accepts($validator, $value, $property_schema);
      if ($omit && is_object($data)) {
        unset($data->{$key});
        continue;
      }
      if ($omit) {
        unset($data[$key]);
        continue;
      }
      if (is_object($data)) {
        $data->{$key} = $refined;
        continue;
      }
      $data[$key] = $refined;
    }
    return $data;
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
   * @param \JsonSchema\Validator $validator
   *   The validator.
   * @param mixed $value
   *   The value.
   * @param object $schema
   *   The schema.
   *
   * @return bool
   *   TRUE when the schema accepts the value.
   */
  private static function accepts(Validator $validator, mixed $value, object $schema): bool {
    $validator->reset();
    $validator->validate($value, $schema);
    return $validator->isValid();
  }

  /**
   * Casts a scalar to the first declared type that can hold it.
   *
   * @param mixed $data
   *   The value.
   * @param array $types
   *   The declared types.
   * @param object $schema
   *   The schema of the value.
   *
   * @return mixed
   *   The cast value, or the value unchanged when no cast applies.
   */
  private static function castScalar(mixed $data, array $types, object $schema): mixed {
    // The first cast that succeeds wins, so the order matters.
    $casts = [
      static fn(mixed &$value): bool => self::tryCastingNumber($value, $types),
      static fn(mixed &$value): bool => self::tryCastingBoolean($value, $types),
      static fn(mixed &$value): bool => self::tryCastingNull($value, $types, $schema),
      static fn(mixed &$value): bool => self::tryCastingString($value, $types),
    ];
    foreach ($casts as $cast) {
      if ($cast($data)) {
        return $data;
      }
    }
    return $data;
  }

  /**
   * Attempts to cast the data to a string.
   *
   * @param mixed $input
   *   The input data. Passed by reference to change its type.
   * @param array $types
   *   The possible types.
   *
   * @return bool
   *   TRUE if casting was possible. FALSE otherwise.
   */
  private static function tryCastingString(mixed &$input, array $types): bool {
    // Arrays and objects have no string spelling; validation reports them.
    $has_string_spelling = is_scalar($input) || $input === NULL;
    if (!$has_string_spelling || !in_array('string', $types, TRUE)) {
      return FALSE;
    }
    $input = (string) $input;
    return TRUE;
  }

  /**
   * Attempts to cast the data to NULL.
   *
   * Only values that already mean "there is nothing here" become NULL. `null`
   * in a type union means the property *also* accepts absence; it does not
   * turn every falsy value the property's other types can hold into absence.
   * FALSE on a `["boolean", "null"]` property and "0" on a
   * `["string", "null"]` one are values the caller chose.
   *
   * The empty string is the one ambiguous case, because Form API submits it
   * for a control the user never touched. It is resolved per type rather than
   * globally: a `string` type can hold it, so it is kept; every other type
   * cannot, so it can only have come from an untouched control and it becomes
   * NULL. A `string` whose shape or length is constrained cannot hold it
   * either -- `minLength`, `format`, `pattern` and an `enum` that does not
   * list "" all rule it out -- so those become NULL too.
   *
   * @param mixed $input
   *   The input data. Passed by reference to change its type.
   * @param array $types
   *   The possible types.
   * @param object $schema
   *   The schema of the property the value belongs to. Read for the keywords
   *   that decide whether a `string` type can hold the empty string.
   *
   * @return bool
   *   TRUE if casting was possible. FALSE otherwise.
   */
  private static function tryCastingNull(mixed &$input, array $types, object $schema): bool {
    if (!in_array('null', $types, TRUE)) {
      return FALSE;
    }
    if ($input === NULL) {
      return TRUE;
    }
    if ($input === '' && !self::acceptsTheEmptyString($types, $schema)) {
      $input = NULL;
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Decides whether any declared type can hold the empty string.
   *
   * @param array $types
   *   The possible types.
   * @param object $schema
   *   The schema of the property the value belongs to.
   *
   * @return bool
   *   TRUE when the empty string is a value the property may hold.
   */
  private static function acceptsTheEmptyString(array $types, object $schema): bool {
    if (!in_array('string', $types, TRUE)) {
      return FALSE;
    }
    $constrains_the_string = ($schema->minLength ?? 0) > 0
      || ($schema->format ?? '') !== ''
      || ($schema->pattern ?? '') !== '';
    if ($constrains_the_string) {
      return FALSE;
    }
    $enum = $schema->enum ?? NULL;
    return !is_array($enum) || in_array('', $enum, TRUE);
  }

  /**
   * Attempts to cast the data to a boolean.
   *
   * @param mixed $input
   *   The input data. Passed by reference to change its type.
   * @param array $types
   *   The possible types.
   *
   * @return bool
   *   TRUE if casting was possible. FALSE otherwise.
   */
  private static function tryCastingBoolean(mixed &$input, array $types): bool {
    $is_quasi_boolean = $input === '0' || $input === '1' || $input === 0 || $input === 1;
    if (!$is_quasi_boolean || !in_array('boolean', $types, TRUE)) {
      return FALSE;
    }
    $input = (bool) $input;
    return TRUE;
  }

  /**
   * Attempts to cast the data to a number.
   *
   * A numeric string becomes an integer when it has no dot and spells a whole
   * number PHP can hold, and a float otherwise. Non-finite values and
   * integers out of range stay strings for validation to report.
   *
   * @param mixed $input
   *   The input data. Passed by reference to change its type.
   * @param array $types
   *   The possible types.
   *
   * @return bool
   *   TRUE if casting was possible. FALSE otherwise.
   */
  private static function tryCastingNumber(mixed &$input, array $types): bool {
    $is_numeric_definition = in_array('integer', $types, TRUE) || in_array('number', $types, TRUE);
    if (!$is_numeric_definition || !is_numeric($input)) {
      return FALSE;
    }
    if (!is_string($input)) {
      return TRUE;
    }
    $number = (float) $input;
    if (!is_finite($number)) {
      return FALSE;
    }
    // An exponent can spell a fraction without a dot, such as "1e-3".
    if (str_contains($input, '.') || floor($number) !== $number) {
      $input = $number;
      return TRUE;
    }
    if (!self::fitsInInteger($input, $number)) {
      return FALSE;
    }
    $input = (int) $input;
    return TRUE;
  }

  /**
   * Checks that a whole numeric string is inside the PHP integer range.
   *
   * @param string $input
   *   The numeric string.
   * @param float $number
   *   Its float value.
   *
   * @return bool
   *   TRUE when casting the string to int keeps its value.
   */
  private static function fitsInInteger(string $input, float $number): bool {
    if ($number < PHP_INT_MIN || $number > PHP_INT_MAX) {
      return FALSE;
    }
    // Float rounding can hide overflow at either integer boundary.
    $at_boundary = $number === (float) PHP_INT_MIN || $number === (float) PHP_INT_MAX;
    return !$at_boundary || filter_var($input, FILTER_VALIDATE_INT) !== FALSE;
  }

}
