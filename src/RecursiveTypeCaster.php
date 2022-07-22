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
    if ($schema->type === 'object') {
      // If the data is NOT an array or object, then do not do any type casting.
      if (!is_array($data) && !is_object($data)) {
        return $data;
      }
      // Handle each property recursively.
      foreach ((array) $data as $key => $value) {
        $data[$key] = static::recursiveTypeRefinements($value, $schema->properties->{$key});
      }
      return $data;
    }
    if ($schema->type === 'array') {
      // If the data is NOT an array, then do not do any type casting.
      if (!is_array($data)) {
        return $data;
      }
      return array_map(static fn(mixed $item) => static::recursiveTypeRefinements($item, $schema->items), $data);
    }
    $types = $schema->type;
    $types = is_array($types) ? $types : [$types];
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
    if (in_array('string', $types)) {
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
    if (in_array('null', $types) && empty($input)) {
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
    if (!in_array('integer', $types) && !in_array('number', $types)) {
      return FALSE;
    }
    if (intval($input) == $input) {
      $input = intval($input);
      return TRUE;
    }
    if (floatval($input) == $input) {
      $input = floatval($input);
      return TRUE;
    }
    return FALSE;
  }

}
