<?php

declare(strict_types=1);

namespace SchemaForms\Drupal;

/**
 * Normalizes saved values and compound defaults for child lookups.
 *
 * @internal
 */
final class SavedInput {

  /**
   * Converts decoded objects to arrays, recursively.
   *
   * Callers may pass saved values as decoded objects or associative arrays,
   * and child controls look their value up by key either way.
   *
   * @param mixed $value
   *   The value.
   *
   * @return mixed
   *   The value with every object and array as an array.
   */
  public static function normalize(mixed $value): mixed {
    if (!is_array($value) && !$value instanceof \stdClass) {
      return $value;
    }
    return array_map([self::class, 'normalize'], (array) $value);
  }

}
