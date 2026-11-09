<?php

declare(strict_types=1);

namespace SchemaForms\Drupal;

use SchemaForms\Exception\InvalidSchemaException;
use SchemaForms\RestrictedSchemaRegistry;
use SchemaForms\SchemaRegistryInterface;

/**
 * Registers a schema for form generation and checks its property names.
 *
 * @internal
 */
final class SchemaPreparer {

  /**
   * Creates the preparer.
   *
   * @param \SchemaForms\SchemaRegistryInterface $registry
   *   Registers the schema, so its references resolve.
   */
  public function __construct(
    private readonly SchemaRegistryInterface $registry = new RestrictedSchemaRegistry(),
  ) {}

  /**
   * Returns the registered copy of a schema.
   *
   * @param mixed $data
   *   The decoded schema.
   *
   * @return \stdClass
   *   The registered copy.
   *
   * @throws \SchemaForms\Exception\UnsupportedReferenceException
   *   When the schema references a document the registry does not allow.
   * @throws \JsonSchema\Exception\RuntimeException
   *   When a reference is not a valid URI.
   * @throws \SchemaForms\Exception\InvalidSchemaException
   *   When the schema is not a decoded object, or a property name starts
   *   with '#'.
   */
  public function prepare(mixed $data): \stdClass {
    if (!is_object($data)) {
      throw new InvalidSchemaException('The schema must be a decoded JSON object.');
    }
    $schema = $this->registry->register($data)->schema;
    if (!$schema instanceof \stdClass) {
      throw new InvalidSchemaException('The schema must be a decoded JSON object.');
    }
    $visited = [];
    $this->rejectRenderPropertyNames($schema, [], $visited);
    return $schema;
  }

  /**
   * Rejects schema property names that Drupal would read as render properties.
   *
   * A name such as '#process' would make Drupal call whatever the schema puts
   * there. The whole schema is checked, including items not rendered yet.
   *
   * @param mixed $schema
   *   The registered schema, or one of its sub-schemas.
   * @param list<string> $path
   *   The schema path of $schema, as keys from the root.
   * @param array<int, true> $visited
   *   The object IDs already checked, so cycles end.
   *
   * @throws \SchemaForms\Exception\InvalidSchemaException
   *   When a property name starts with '#'.
   */
  private function rejectRenderPropertyNames(mixed $schema, array $path, array &$visited): void {
    if (!is_object($schema) || isset($visited[spl_object_id($schema)])) {
      return;
    }
    $visited[spl_object_id($schema)] = TRUE;
    $properties = $schema->properties ?? NULL;
    foreach (is_object($properties) || is_array($properties) ? (array) $properties : [] as $name => $sub_schema) {
      $sub_path = [...$path, 'properties', (string) $name];
      if (str_starts_with((string) $name, '#')) {
        throw new InvalidSchemaException(sprintf(
          'Schema property "%s" starts with "#", which Drupal reserves for render properties.',
          implode('.', $sub_path)
        ));
      }
      $this->rejectRenderPropertyNames($sub_schema, $sub_path, $visited);
    }
    $this->rejectRenderPropertyNames($schema->items ?? NULL, [...$path, 'items'], $visited);
  }

}
