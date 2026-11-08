<?php

declare(strict_types=1);

namespace SchemaForms\Drupal;

use SchemaForms\SchemaReferenceResolver;

/**
 * Registers a schema for form generation and checks its property names.
 *
 * @internal
 */
final class SchemaPreparer {

  /**
   * Returns the registered copy of a schema.
   *
   * @param mixed $data
   *   The decoded schema.
   *
   * @return \stdClass
   *   The registered copy, with normalized reference URIs. Referenced
   *   properties are not expanded.
   *
   * @throws \SchemaForms\Exception\UnsupportedReferenceException
   *   When the schema references a document outside itself.
   * @throws \JsonSchema\Exception\RuntimeException
   *   When a reference is not a valid URI.
   * @throws \InvalidArgumentException
   *   When the schema is not a decoded object, or a property name starts
   *   with '#'.
   */
  public function prepare(mixed $data): \stdClass {
    if (!is_object($data)) {
      throw new \InvalidArgumentException('The schema must be a decoded JSON object.');
    }
    $schema = SchemaReferenceResolver::registeredSchema(SchemaReferenceResolver::createFactory($data));
    if (!$schema instanceof \stdClass) {
      throw new \InvalidArgumentException('The schema must be a decoded JSON object.');
    }
    $visited = [];
    $this->rejectRenderPropertyNames($schema, [], $visited);
    return $schema;
  }

  /**
   * Rejects schema property names that Drupal would read as render properties.
   *
   * Property names become render-array keys. A name such as '#process' or
   * '#element_validate' would make Drupal call whatever the schema puts there,
   * and '#type' or '#tree' would corrupt the form. The schema is checked as a
   * whole before any element is built, so array items that are not rendered
   * yet are covered too.
   *
   * @param mixed $schema
   *   The registered schema, or one of its sub-schemas.
   * @param list<string> $path
   *   The schema path of $schema, as keys from the root.
   * @param array<int, true> $visited
   *   The object IDs of the sub-schemas already checked, so that a schema
   *   object graph with cycles cannot recurse forever.
   *
   * @throws \InvalidArgumentException
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
        throw new \InvalidArgumentException(sprintf(
          'Schema property "%s" starts with "#", which Drupal reserves for render properties.',
          implode('.', $sub_path)
        ));
      }
      $this->rejectRenderPropertyNames($sub_schema, $sub_path, $visited);
    }
    $this->rejectRenderPropertyNames($schema->items ?? NULL, [...$path, 'items'], $visited);
  }

}
