<?php

declare(strict_types=1);

namespace SchemaForms;

use JsonSchema\Constraints\Factory;
use JsonSchema\Validator;

/**
 * Casts a data structure to its JSON-Schema the best it can.
 *
 * Walks lists and objects with their item and property schemas. Scalars go
 * to ScalarCaster, and OptionalBlankRule decides which untouched properties
 * to leave out.
 *
 * @internal
 */
final class RecursiveTypeCaster {

  /**
   * Starts a refinement.
   *
   * @param \SchemaForms\OptionalBlankRule $blanks
   *   Decides which blank properties to omit.
   */
  private function __construct(
    private readonly OptionalBlankRule $blanks,
  ) {}

  /**
   * Refines data against a registered schema.
   *
   * @param mixed $data
   *   The data.
   * @param \SchemaForms\RegisteredSchema $schema
   *   The schema and the factory that resolves its references. Optional
   *   blanks are validated with that factory, in its configured check mode.
   *
   * @return mixed
   *   The same data with refined types.
   *
   * @throws \JsonSchema\Exception\InvalidArgumentException
   *   When a reference inside the schema cannot be resolved.
   * @throws \JsonSchema\Exception\RuntimeException
   *   When a reference is not a valid URI.
   */
  public static function refine(mixed $data, RegisteredSchema $schema): mixed {
    return (new self(new OptionalBlankRule(new Validator($schema->factory))))->refineValue($data, $schema->schema);
  }

  // phpcs:disable Drupal.Commenting.Deprecated.DeprecatedWrongSeeUrlFormat, Drupal.Semantics.FunctionTriggerError.TriggerErrorSeeUrlFormat -- The project is not on drupal.org.

  /**
   * Takes a data structure and tries its best to fit the types in the schema.
   *
   * @param mixed $data
   *   The data.
   * @param object $schema
   *   The schema. Without a factory, a copy of it is registered and that copy
   *   is walked.
   * @param \JsonSchema\Constraints\Factory|null $factory
   *   A factory whose storage holds $schema, references expanded.
   *
   * @return mixed
   *   The same data with refined types.
   *
   * @throws \SchemaForms\Exception\UnsupportedReferenceException
   *   When the schema references a document outside itself.
   * @throws \JsonSchema\Exception\InvalidArgumentException
   *   When a reference inside the schema cannot be resolved.
   * @throws \JsonSchema\Exception\RuntimeException
   *   When a reference is not a valid URI.
   *
   * @deprecated in schema_forms:3.2.0 and is removed from schema_forms:4.0.0.
   *   Use refine() with a RegisteredSchema, or SubmissionRefiner::refine().
   *
   * @see https://github.com/e0ipso/schema-forms-php#upgrading
   */
  public static function recursiveTypeRefinements(mixed $data, object $schema, ?Factory $factory = NULL): mixed {
    @trigger_error('RecursiveTypeCaster::recursiveTypeRefinements() is deprecated in schema_forms:3.2.0 and is removed from schema_forms:4.0.0. Use SubmissionRefiner::refine() instead. See https://github.com/e0ipso/schema-forms-php#upgrading', E_USER_DEPRECATED);
    $registered = $factory === NULL
      ? (new RestrictedSchemaRegistry())->register($schema)
      : new RegisteredSchema($schema, $factory);
    return self::refine($data, $registered);
  }

  // phpcs:enable

  /**
   * Refines a value against the schema that describes it.
   *
   * @param mixed $data
   *   The data.
   * @param object $schema
   *   The schema for this value.
   *
   * @return mixed
   *   The same data with refined types.
   */
  private function refineValue(mixed $data, object $schema): mixed {
    $enum = $schema->enum ?? NULL;
    if (!isset($schema->type) && !empty($enum) && (is_string($data) || is_int($data))) {
      return self::enumMember($data, $enum);
    }
    $types = SchemaType::listOf($schema);
    if (is_array($data) && array_is_list($data) && in_array('array', $types, TRUE)) {
      return $this->refineItems($data, $schema);
    }
    if ((is_array($data) || is_object($data)) && in_array('object', $types, TRUE)) {
      return $this->refineProperties($data, $schema);
    }
    return ScalarCaster::cast($data, $types, $schema);
  }

  /**
   * Refines each item of a list with the item schema.
   *
   * @param list<mixed> $data
   *   The list.
   * @param object $schema
   *   The array schema.
   *
   * @return list<mixed>
   *   The refined list.
   */
  private function refineItems(array $data, object $schema): array {
    $items = $schema->items ?? NULL;
    // Missing or list-form items give no single item schema to cast against.
    if (!is_object($items)) {
      return $data;
    }
    return array_map(fn(mixed $item): mixed => $this->refineValue($item, $items), $data);
  }

  /**
   * Refines each property and omits optional blanks the schema rejects.
   *
   * A stdClass is changed in place, so it stays an object at every level.
   *
   * @param array<mixed>|object $data
   *   The object data.
   * @param object $schema
   *   The object schema.
   *
   * @return array<mixed>|object
   *   The refined data.
   */
  private function refineProperties(array|object $data, object $schema): array|object {
    foreach ((array) $data as $key => $value) {
      $property_schema = self::propertySchema($schema, (string) $key);
      // A property the schema does not describe is left for validation.
      if ($property_schema === NULL) {
        continue;
      }
      $refined = $this->refineValue($value, $property_schema);
      $omit = $this->blanks->omits($key, $value, $refined, $schema, $property_schema);
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
   * Returns the enum member a submitted option key stands for.
   *
   * Forms submit option keys as strings or integers, so an untyped enum of
   * other scalars needs its members back.
   *
   * @param string|int $data
   *   The submitted option key.
   * @param mixed $enum
   *   The enum keyword of an untyped schema.
   *
   * @return mixed
   *   The matching member, or the data when no member matches.
   */
  private static function enumMember(string|int $data, mixed $enum): mixed {
    $members = [];
    // A decoded map lists its members as properties.
    foreach (is_array($enum) || is_object($enum) ? (array) $enum : [] as $member) {
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
  private static function propertySchema(object $schema, string $name): ?object {
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
