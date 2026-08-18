<?php

namespace SchemaForms;

/**
 * Casts a data structure to its JSON-Schema the best it can.
 */
final class RecursiveTypeCaster {

  /**
   * Takes a data structure and tries its best to fit the types in the schema.
   *
   * @param mixed $data
   *   The data.
   * @param object $schema
   *   The schema.
   *
   * @return mixed
   *   The same data with refined types.
   */
  public static function recursiveTypeRefinements($data, object $schema) {
    $types = $schema->type;
    $types = is_array($types) ? $types : [$types];
    if (is_array($data) && array_is_list($data) && in_array('array', $types, TRUE)) {
      return array_map(static fn($item) => static::recursiveTypeRefinements($item, $schema->items), $data);
    }
    if ((is_array($data) || is_object($data)) && in_array('object', $types, TRUE)) {
      // If the data is NOT an array or object, then do not do any type casting.
      if (!is_array($data) && !is_object($data)) {
        return $data;
      }
      // Handle each property recursively.
      foreach ((array) $data as $key => $value) {
        $sub_schema = $schema->properties->{$key} ?? $schema->items ?? (object) ['type' => 'null'];
        $data[$key] = static::recursiveTypeRefinements($value, $sub_schema);
      }
      return $data;
    }
    array_reduce([
      static fn(&$data, $types) => static::tryCastingNumber($data, $types),
      static fn(&$data, $types) => static::tryCastingBoolean($data, $types),
      static fn(&$data, $types) => static::tryCastingNull($data, $types, $schema),
      static fn(&$data, $types) => static::tryCastingString($data, $types),
    ],
      static function (bool $casted, callable $method) use (&$data, $types) {
        return $casted ?: $method($data, $types);
      },
      FALSE
    );
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
  private static function tryCastingString(&$input, array $types): bool {
    if (in_array('string', $types, TRUE)) {
      $input = (string) $input;
      return TRUE;
    }
    return FALSE;
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
  private static function tryCastingNull(&$input, array $types, object $schema): bool {
    if (!in_array('null', $types, TRUE)) {
      return FALSE;
    }
    if ($input === NULL) {
      return TRUE;
    }
    if ($input === '' && !static::acceptsTheEmptyString($types, $schema)) {
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
  private static function tryCastingBoolean(&$input, array $types): bool {
    $is_quasi_boolean = $input === '0' || $input === '1' || $input === 0 || $input === 1;
    if (in_array('boolean', $types) && $is_quasi_boolean) {
      $input = (boolean) $input;
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Attempts to cast the data to a number.
   *
   * @param mixed $input
   *   The input data. Passed by reference to change its type.
   * @param array $types
   *   The possible types.
   *
   * @return bool
   *   TRUE if casting was possible. FALSE otherwise.
   */
  private static function tryCastingNumber(&$input, array $types): bool {
    $is_not_numeric_definition = !in_array('integer', $types, TRUE)
      && !in_array('number', $types, TRUE);
    if ($is_not_numeric_definition || !is_numeric($input)) {
      return FALSE;
    }
    if (is_int($input) || is_float($input)) {
      return TRUE;
    }
    if (is_string($input)) {
      // This conversion is guaranteed because of the is_numeric check above.
      $input = strpos($input, '.') === FALSE
        ? (int) $input
        : (float) $input;
      return TRUE;
    }
    return FALSE;
  }

}
