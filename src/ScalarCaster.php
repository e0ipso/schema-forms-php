<?php

declare(strict_types=1);

namespace SchemaForms;

/**
 * Casts a submitted scalar to the first type its schema declares that fits.
 *
 * @internal
 */
final class ScalarCaster {

  /**
   * Casts a scalar to the first declared type that can hold it.
   *
   * @param mixed $data
   *   The value.
   * @param array<mixed> $types
   *   The declared types.
   * @param object $schema
   *   The schema of the value.
   *
   * @return mixed
   *   The cast value, or the value unchanged when no cast applies.
   */
  public static function cast(mixed $data, array $types, object $schema): mixed {
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
   * @param array<mixed> $types
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
   * @param array<mixed> $types
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
   * @param array<mixed> $types
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
   * @param array<mixed> $types
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
   * @param array<mixed> $types
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
