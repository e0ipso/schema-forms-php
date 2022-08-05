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
  public static function recursiveTypeRefinements(mixed $data, object $schema): mixed {
    $types = $schema->type;
    $types = is_array($types) ? $types : [$types];
    if (is_array($data) && array_is_list($data) && in_array('array', $types, TRUE)) {
      return array_map(static fn(mixed $item) => static::recursiveTypeRefinements($item, $schema->items), $data);
    }
    if ((is_array($data) || is_object($data)) && in_array('object', $types, TRUE)) {
      // If the data is NOT an array or object, then do not do any type casting.
      if (!is_array($data) && !is_object($data)) {
        return $data;
      }
      // Handle each property recursively.
      foreach ((array) $data as $key => $value) {
        $sub_schema = $schema->properties->{$key} ?? $schema->items ?? ['type' => 'null'];
        $data[$key] = static::recursiveTypeRefinements($value, $sub_schema);
      }
      return $data;
    }
    array_reduce([
      static fn(&$data, $types) => static::tryCastingNumber($data, $types),
      static fn(&$data, $types) => static::tryCastingBoolean($data, $types),
      static fn(&$data, $types) => static::tryCastingNull($data, $types),
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
  private static function tryCastingString(mixed &$input, array $types): bool {
    if (in_array('string', $types, TRUE)) {
      $input = (string) $input;
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Attempts to cast the data to NULL.
   *
   * @param mixed $input
   *   The input data. Passed by reference to change its type.
   * @param array $types
   *   The possible types.
   *
   * @return bool
   *   TRUE if casting was possible. FALSE otherwise.
   */
  private static function tryCastingNull(mixed &$input, array $types): bool {
    if (in_array('null', $types, TRUE) && empty($input)) {
      $input = NULL;
      return TRUE;
    }
    return FALSE;
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
    if (in_array('boolean', $types) && ($input == '0' || $input == '1')) {
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
  private static function tryCastingNumber(mixed &$input, array $types): bool {
    $is_not_numeric = !in_array('integer', $types, TRUE)
      && !in_array('number', $types, TRUE);
    if ($is_not_numeric) {
      return FALSE;
    }
    if ((int) $input == $input) {
      $input = (int) $input;
      return TRUE;
    }
    if ((float) $input == $input) {
      $input = (float) $input;
      return TRUE;
    }
    return FALSE;
  }

}
