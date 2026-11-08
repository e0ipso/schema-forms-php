<?php

declare(strict_types=1);

namespace SchemaForms;

use JsonSchema\Entity\JsonPointer;
use JsonSchema\SchemaStorageInterface;
use SchemaForms\Exception\UnsupportedReferenceException;

/**
 * Checks reference targets even in branches validation may not evaluate.
 *
 * Optional blank values can be omitted before final validation, so checking
 * only references followed by the validator would silently accept some
 * unsupported schemas. Traverse schema keywords, not literal default, enum or
 * const values. The retriever remains the boundary for every actual fetch.
 *
 * @internal
 */
final class ReferenceChecker {

  /**
   * Keywords whose value is a map of schemas.
   */
  private const SCHEMA_MAPS = [
    'properties', 'patternProperties', 'definitions', '$defs', 'dependencies',
    'dependentSchemas',
  ];

  /**
   * Keywords whose value is a schema or a list of schemas.
   */
  private const SCHEMA_VALUES = [
    'items', 'additionalItems', 'additionalProperties',
    'allOf', 'anyOf', 'oneOf', 'not', 'extends', 'type', 'disallow',
    'contains', 'propertyNames', 'if', 'then', 'else',
  ];

  /**
   * Object IDs already checked.
   *
   * @var array<int, true>
   */
  private array $visited = [];

  /**
   * Starts a check that resolves targets from the given storage.
   *
   * @param \JsonSchema\SchemaStorageInterface $storage
   *   Storage containing the root schema and any registered subschemas.
   */
  private function __construct(
    private readonly SchemaStorageInterface $storage,
  ) {}

  /**
   * Loads the target of every reference in a schema, through the storage.
   *
   * @param object $schema
   *   The registered schema.
   * @param \JsonSchema\SchemaStorageInterface $storage
   *   Storage containing the root schema and any registered subschemas.
   *
   * @throws \SchemaForms\Exception\UnsupportedReferenceException
   *   When a reference is not a string, or its target is outside the
   *   registered documents.
   * @throws \JsonSchema\Exception\RuntimeException
   *   When a reference is not a valid URI.
   */
  public static function check(object $schema, SchemaStorageInterface $storage): void {
    (new self($storage))->visit($schema);
  }

  /**
   * Checks a schema or a list of schemas.
   *
   * @param mixed $schema
   *   A schema or a list of schemas.
   */
  private function visit(mixed $schema): void {
    if (is_array($schema)) {
      foreach ($schema as $member) {
        $this->visit($member);
      }
      return;
    }
    if (!is_object($schema) || isset($this->visited[spl_object_id($schema)])) {
      return;
    }
    $this->visited[spl_object_id($schema)] = TRUE;
    if (isset($schema->{'$ref'})) {
      if (!is_string($schema->{'$ref'})) {
        throw new UnsupportedReferenceException('A "$ref" value must be a string.');
      }
      $target = (new JsonPointer($schema->{'$ref'}))->getFilename();
      // Checking the document does not expand recursive internal references.
      $this->visit($this->storage->getSchema($target));
    }
    if (isset($schema->extends) && is_string($schema->extends)) {
      $target = SchemaUriResolver::resolve($schema->id ?? NULL, $schema->extends);
      $this->visit($this->storage->getSchema($target));
    }
    foreach (self::SCHEMA_MAPS as $keyword) {
      $this->visit(array_values((array) ($schema->{$keyword} ?? [])));
    }
    foreach (self::SCHEMA_VALUES as $keyword) {
      $this->visit($schema->{$keyword} ?? NULL);
    }
  }

}
