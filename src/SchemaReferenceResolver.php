<?php

namespace SchemaForms;

use JsonSchema\Constraints\Factory;
use JsonSchema\Entity\JsonPointer;
use JsonSchema\SchemaStorage;
use JsonSchema\SchemaStorageInterface;
use JsonSchema\Uri\UriResolver;
use JsonSchema\UriRetrieverInterface;
use SchemaForms\Exception\UnsupportedReferenceException;

/**
 * Keeps schema retrieval inside registered documents and bundled metadata.
 *
 * @internal
 */
final class SchemaReferenceResolver implements UriRetrieverInterface {

  /**
   * The storage key shared by generation and submission validation.
   */
  public const SCHEMA_URI = 'internal:/schema-with-refs';

  /**
   * Creates a factory with the same restricted retriever on both entry points.
   *
   * @param object|null $schema
   *   The schema to register and check before rendering or casting values.
   *
   * @return \JsonSchema\Constraints\Factory
   *   A factory which cannot retrieve external schemas.
   *
   * @throws \SchemaForms\Exception\UnsupportedReferenceException
   *   When the schema references a document outside itself.
   * @throws \JsonSchema\Exception\ExceptionInterface
   *   When a reference inside the schema cannot be resolved.
   */
  public static function createFactory(?object $schema = NULL): Factory {
    $retriever = new self();
    $storage = new SchemaStorage($retriever);
    $factory = new Factory($storage, $retriever);
    if ($schema !== NULL) {
      // Storage rewrites references in place, so register a copy and leave
      // the caller's schema as it was.
      $registered = self::copySchema($schema, new \WeakMap());
      // Older releases treat a single-slash internal URI as a local file base.
      // Expand local references with a URI both supported majors recognize,
      // then retain the original storage key for explicitly registered refs.
      $storage->addSchema('internal://schema-with-refs/', $registered);
      $storage->addSchema(self::SCHEMA_URI, $registered);
      $visited = [];
      self::checkReferences($registered, $storage, $visited);
    }
    return $factory;
  }

  /**
   * Returns the schema registered by createFactory(), references expanded.
   *
   * Its subschemas can be validated on their own with the same factory,
   * because every reference in them names its document.
   *
   * @param \JsonSchema\Constraints\Factory $factory
   *   A factory returned by createFactory() for a schema.
   *
   * @return object
   *   The registered copy of the schema.
   */
  public static function registeredSchema(Factory $factory): object {
    return $factory->getSchemaStorage()->getSchema(self::SCHEMA_URI);
  }

  /**
   * Copies the decoded objects and arrays of a schema.
   *
   * Objects other than stdClass are not schema data and stay shared.
   *
   * @param mixed $value
   *   The schema or one of its members.
   * @param \WeakMap $copies
   *   Copies already made, so shared and cyclic members keep their shape.
   *
   * @return mixed
   *   The copy.
   */
  private static function copySchema(mixed $value, \WeakMap $copies): mixed {
    if (is_array($value)) {
      return array_map(static fn(mixed $member): mixed => self::copySchema($member, $copies), $value);
    }
    if (!$value instanceof \stdClass) {
      return $value;
    }
    if (isset($copies[$value])) {
      return $copies[$value];
    }
    $copy = clone $value;
    $copies[$value] = $copy;
    foreach ($copy as &$member) {
      $member = self::copySchema($member, $copies);
    }
    return $copy;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \SchemaForms\Exception\UnsupportedReferenceException
   *   For every URI except the bundled draft-04 meta-schema.
   */
  public function retrieve($uri, $baseUri = NULL) {
    $resolved = (new UriResolver())->resolve($uri, $baseUri);
    $metadata_path = dirname(__DIR__) . '/meta-schema-draft4.json';
    // Registered schemas are served by SchemaStorage without a retrieval. Only
    // these exact metadata URIs may reach disk. Never pass a target to a
    // generic retriever, even when its scheme is file or internal.
    if (!in_array($resolved, [
      'file://' . $metadata_path,
      'http://json-schema.org/draft-04/schema',
      'http://json-schema.org/draft-04/schema#',
      'https://json-schema.org/draft-04/schema',
      'https://json-schema.org/draft-04/schema#',
    ], TRUE)) {
      throw new UnsupportedReferenceException('External schema retrieval is disabled.');
    }
    return json_decode(file_get_contents($metadata_path), FALSE, 512, JSON_THROW_ON_ERROR);
  }

  /**
   * Checks reference targets even in branches validation may not evaluate.
   *
   * Optional blank values can be omitted before final validation, so checking
   * only references followed by the validator would silently accept some
   * unsupported schemas. Traverse schema keywords, not literal default, enum
   * or const values. The retriever remains the boundary for every actual fetch.
   *
   * @param mixed $schema
   *   A schema or a list of schemas.
   * @param \JsonSchema\SchemaStorageInterface $storage
   *   Storage containing the root schema and any registered subschemas.
   * @param array $visited
   *   Object IDs already checked.
   */
  private static function checkReferences($schema, SchemaStorageInterface $storage, array &$visited): void {
    if (is_array($schema)) {
      foreach ($schema as $member) {
        self::checkReferences($member, $storage, $visited);
      }
      return;
    }
    if (!is_object($schema) || isset($visited[spl_object_id($schema)])) {
      return;
    }
    $visited[spl_object_id($schema)] = TRUE;
    if (isset($schema->{'$ref'})) {
      $target = (new JsonPointer($schema->{'$ref'}))->getFilename();
      // Checking the document does not expand recursive internal references.
      self::checkReferences($storage->getSchema($target), $storage, $visited);
    }
    if (isset($schema->extends) && is_string($schema->extends)) {
      $target = (new UriResolver())->resolve($schema->id ?? NULL, $schema->extends);
      self::checkReferences($storage->getSchema($target), $storage, $visited);
    }
    foreach (['properties', 'patternProperties', 'definitions', '$defs', 'dependencies', 'dependentSchemas'] as $keyword) {
      foreach ((array) ($schema->{$keyword} ?? []) as $member) {
        self::checkReferences($member, $storage, $visited);
      }
    }
    foreach ([
      'items', 'additionalItems', 'additionalProperties',
      'allOf', 'anyOf', 'oneOf', 'not', 'extends', 'type', 'disallow',
      'contains', 'propertyNames', 'if', 'then', 'else',
    ] as $keyword) {
      self::checkReferences($schema->{$keyword} ?? NULL, $storage, $visited);
    }
  }

}
