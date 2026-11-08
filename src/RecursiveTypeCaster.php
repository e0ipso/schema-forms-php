<?php

declare(strict_types=1);

namespace SchemaForms;

use JsonSchema\Constraints\Factory;
use JsonSchema\Validator;

/**
 * Casts a data structure to its JSON-Schema the best it can.
 *
 * Walks lists and objects with their item and property schemas. Scalars go
 * to ScalarCaster and EnumMemberMatcher, and OptionalBlankRule decides which
 * untouched properties to leave out.
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
   * @throws \JsonSchema\Exception\InvalidArgumentException
   *   When a reference inside the schema cannot be resolved.
   * @throws \JsonSchema\Exception\RuntimeException
   *   When a reference is not a valid URI.
   */
  public static function recursiveTypeRefinements(mixed $data, object $schema, ?Factory $factory = NULL): mixed {
    if ($factory === NULL) {
      $factory = SchemaReferenceResolver::createFactory($schema);
      // Walk the registered copy: its references resolve from any subschema.
      $schema = SchemaReferenceResolver::registeredSchema($factory);
    }
    return (new self(new OptionalBlankRule(new Validator($factory))))->refineValue($data, $schema);
  }

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
      return EnumMemberMatcher::match($data, $enum);
    }
    $types = self::types($schema);
    if (is_array($data) && array_is_list($data) && in_array('array', $types, TRUE)) {
      return $this->refineItems($data, $schema);
    }
    if ((is_array($data) || is_object($data)) && in_array('object', $types, TRUE)) {
      return $this->refineProperties($data, $schema);
    }
    return ScalarCaster::cast($data, $types, $schema);
  }

  /**
   * Lists the types a schema declares.
   *
   * @param object $schema
   *   The schema.
   *
   * @return array<mixed>
   *   The declared types; empty when the schema declares none.
   */
  private static function types(object $schema): array {
    $types = $schema->type ?? [];
    return is_array($types) ? $types : [$types];
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
      $property_schema = PropertySchemaResolver::resolve($schema, (string) $key);
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

}
