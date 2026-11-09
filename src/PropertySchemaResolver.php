<?php

declare(strict_types=1);

namespace SchemaForms;

/**
 * Finds the schema that describes one property of an object schema.
 *
 * @internal
 */
final class PropertySchemaResolver {

  /**
   * Finds the schema that describes one property of an object.
   *
   * A declared property wins, then the first matching pattern property, then
   * an additionalProperties schema.
   *
   * @param object $schema
   *   The object schema.
   * @param string $name
   *   The property name.
   *
   * @return object|null
   *   The property schema, or NULL when the schema does not describe it.
   */
  public static function resolve(object $schema, string $name): ?object {
    $declared = $schema->properties->{$name} ?? NULL;
    if (is_object($declared)) {
      return $declared;
    }
    // A scalar keyword value casts to a list of one non-object, which is
    // skipped like any member that is not a schema.
    foreach ((array) ($schema->patternProperties ?? []) as $pattern => $pattern_schema) {
      if (is_object($pattern_schema) && self::matchesPattern((string) $pattern, $name)) {
        return $pattern_schema;
      }
    }
    $additional = $schema->additionalProperties ?? NULL;
    return is_object($additional) ? $additional : NULL;
  }

  /**
   * Matches a property name against a JSON Schema pattern.
   *
   * @param string $pattern
   *   The pattern, without delimiters.
   * @param string $name
   *   The property name.
   *
   * @return bool
   *   TRUE on a match. An invalid pattern matches nothing; validation reports
   *   it.
   */
  private static function matchesPattern(string $pattern, string $name): bool {
    // Escape each "~" that no backslash escapes already, so the delimiter
    // cannot end the pattern early. "\~" stays as it is.
    $escaped = preg_replace('/(?<!\\\\)((?:\\\\\\\\)*)~/', '$1\\~', $pattern);
    // preg_match() warns about an invalid pattern and returns FALSE. Keep the
    // warning out of the host's error log; FALSE matches nothing.
    set_error_handler(static fn (): bool => TRUE);
    try {
      $matched = $escaped === NULL ? FALSE : preg_match('~' . $escaped . '~u', $name);
    }
    finally {
      restore_error_handler();
    }
    return $matched === 1;
  }

}
