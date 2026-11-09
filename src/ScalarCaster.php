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
   * @param list<string> $types
   *   The declared types.
   * @param object $schema
   *   The schema of the value.
   *
   * @return mixed
   *   The cast value, or the value unchanged when no cast applies.
   */
  public static function cast(mixed $data, array $types, object $schema): mixed {
    // The first cast that applies wins, so the order matters. A numeric
    // string a number cannot hold stays as it is: no later cast changes it.
    if (is_numeric($data) && array_intersect(['integer', 'number'], $types) !== []) {
      return is_string($data) ? self::castNumericString($data) : $data;
    }
    if (in_array($data, ['0', '1', 0, 1], TRUE) && in_array('boolean', $types, TRUE)) {
      return (bool) $data;
    }
    if (in_array('null', $types, TRUE) && self::meansAbsence($data, $types, $schema)) {
      return NULL;
    }
    return self::castToString($data, $types);
  }

  /**
   * Casts the data to a string, as the last resort.
   *
   * @param mixed $input
   *   The input data.
   * @param list<string> $types
   *   The possible types.
   *
   * @return mixed
   *   The string, or the input unchanged when it cannot be one.
   */
  private static function castToString(mixed $input, array $types): mixed {
    // Arrays and objects have no string spelling; validation reports them.
    $has_string_spelling = is_scalar($input) || $input === NULL;
    if (!$has_string_spelling || !in_array('string', $types, TRUE)) {
      return $input;
    }
    return (string) $input;
  }

  /**
   * Decides whether a value on a nullable property means "nothing here".
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
   *   The input data.
   * @param list<string> $types
   *   The possible types.
   * @param object $schema
   *   The schema of the property the value belongs to. Read for the keywords
   *   that decide whether a `string` type can hold the empty string.
   *
   * @return bool
   *   TRUE when the value becomes NULL.
   */
  private static function meansAbsence(mixed $input, array $types, object $schema): bool {
    return $input === NULL || ($input === '' && !self::acceptsTheEmptyString($types, $schema));
  }

  /**
   * Decides whether any declared type can hold the empty string.
   *
   * @param list<string> $types
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
   * Casts a numeric string to the number it spells.
   *
   * A numeric string becomes an integer when it has no dot and spells a whole
   * number PHP can hold, and a float otherwise. Non-finite values and
   * integers out of range stay strings for validation to report.
   *
   * @param numeric-string $input
   *   The numeric string.
   *
   * @return int|float|string
   *   The number, or the string unchanged when no number holds it.
   */
  private static function castNumericString(string $input): int|float|string {
    $number = (float) $input;
    if (!is_finite($number)) {
      return $input;
    }
    // An exponent can spell a fraction without a dot, such as "1e-3".
    if (str_contains($input, '.') || floor($number) !== $number) {
      return $number;
    }
    return self::fitsInInteger($input, $number) ? (int) $input : $input;
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
