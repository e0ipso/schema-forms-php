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
    foreach (self::patternSchemas($schema) as $pattern => $pattern_schema) {
      if (is_object($pattern_schema) && self::matchesPattern((string) $pattern, $name)) {
        return $pattern_schema;
      }
    }
    $additional = $schema->additionalProperties ?? NULL;
    return is_object($additional) ? $additional : NULL;
  }

  /**
   * Lists the pattern property schemas of an object schema.
   *
   * @param object $schema
   *   The object schema.
   *
   * @return array<mixed>
   *   The schemas keyed by pattern; empty when the keyword is not a map.
   */
  private static function patternSchemas(object $schema): array {
    $patterns = $schema->patternProperties ?? [];
    if (is_object($patterns)) {
      return get_object_vars($patterns);
    }
    return is_array($patterns) ? $patterns : [];
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
    return @preg_match('~' . str_replace('~', '\~', $pattern) . '~u', $name) === 1;
  }

}
