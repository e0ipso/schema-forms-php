<?php

declare(strict_types=1);

namespace SchemaForms;

use JsonSchema\Constraints\Factory;
use JsonSchema\Validator;

/**
 * Casts a data structure to its JSON-Schema the best it can.
 *
 * Walks lists and objects with their item and property schemas, omits
 * optional blanks the schema rejects, and leaves scalars to ScalarCaster and
 * EnumMemberMatcher.
 *
 * @internal
 */
final class RecursiveTypeCaster {

  /**
   * Starts a refinement that checks optional blanks with the validator.
   *
   * @param \JsonSchema\Validator $validator
   *   Validates optional blanks; its factory resolves the root's references.
   */
  private function __construct(
    private readonly Validator $validator,
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
    return (new self(new Validator($factory)))->refineValue($data, $schema);
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
      // Nullable refinements take precedence over omitting untouched fields.
      $omit = $value === '' && $refined === ''
        && !self::isRequired($key, $schema)
        && !$this->accepts($value, $property_schema);
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
   * Checks whether the object schema lists a property as required.
   *
   * PHP turns a decimal property name such as "123" into an integer array key,
   * while the schema lists it as a string.
   *
   * @param int|string $key
   *   The property name, as PHP keys it.
   * @param object $schema
   *   The object schema.
   *
   * @return bool
   *   TRUE when the property is required.
   */
  private static function isRequired(int|string $key, object $schema): bool {
    $required = $schema->required ?? [];
    if (!is_array($required)) {
      return FALSE;
    }
    return in_array((string) $key, $required, TRUE) || in_array($key, $required, TRUE);
  }

  /**
   * Checks a value against a schema.
   *
   * @param mixed $value
   *   The value.
   * @param object $schema
   *   The schema.
   *
   * @return bool
   *   TRUE when the schema accepts the value.
   */
  private function accepts(mixed $value, object $schema): bool {
    $this->validator->reset();
    $this->validator->validate($value, $schema);
    return $this->validator->isValid();
  }

}
