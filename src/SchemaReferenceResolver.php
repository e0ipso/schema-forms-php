<?php

namespace SchemaForms;

use JsonSchema\Constraints\Factory;
use JsonSchema\Entity\JsonPointer;
use JsonSchema\Exception\UnresolvableJsonPointerException;
use JsonSchema\SchemaStorage;
use JsonSchema\SchemaStorageInterface;
use JsonSchema\Uri\UriResolver;
use JsonSchema\UriRetrieverInterface;

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
   */
  public static function createFactory(?object $schema = NULL): Factory {
    $retriever = new self();
    $storage = new SchemaStorage($retriever);
    $factory = new Factory($storage, $retriever);
    if ($schema !== NULL) {
      // Older releases treat a single-slash internal URI as a local file base.
      // Expand local references with a URI both supported majors recognize,
      // then retain the original storage key for explicitly registered refs.
      $storage->addSchema('internal://schema-with-refs/', $schema);
      $storage->addSchema(self::SCHEMA_URI, $schema);
      $visited = [];
      self::checkReferences($schema, $storage, $visited);
    }
    return $factory;
  }

  /**
   * {@inheritdoc}
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
      throw new UnresolvableJsonPointerException('External schema retrieval is disabled.');
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
