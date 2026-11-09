<?php

declare(strict_types=1);

namespace SchemaForms;

use JsonSchema\Constraints\Factory;
use JsonSchema\SchemaStorage;
use JsonSchema\UriRetrieverInterface;
use SchemaForms\Exception\UnreadableMetaSchemaException;
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
   * @throws \JsonSchema\Exception\RuntimeException
   *   When a reference is not a valid URI.
   */
  public static function createFactory(?object $schema = NULL): Factory {
    $retriever = new self();
    $storage = new SchemaStorage($retriever);
    $factory = new Factory($storage, $retriever);
    if ($schema !== NULL) {
      // Storage rewrites references in place, so register a copy and leave
      // the caller's schema as it was.
      $registered = SchemaCopier::copy($schema);
      // Older releases treat a single-slash internal URI as a local file base.
      // Expand local references with a URI both supported majors recognize,
      // then retain the original storage key for explicitly registered refs.
      $storage->addSchema('internal://schema-with-refs/', $registered);
      $storage->addSchema(self::SCHEMA_URI, $registered);
      ReferenceChecker::check($registered, $storage);
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
    $schema = $factory->getSchemaStorage()->getSchema(self::SCHEMA_URI);
    // createFactory() registers an object, never a boolean schema.
    assert(is_object($schema));
    return $schema;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \SchemaForms\Exception\UnsupportedReferenceException
   *   For every URI except the bundled draft-04 meta-schema.
   * @throws \SchemaForms\Exception\UnreadableMetaSchemaException
   *   When the bundled meta-schema cannot be read.
   */
  #[\Override]
  public function retrieve(mixed $uri, mixed $baseUri = NULL): object {
    $resolved = SchemaUriResolver::resolve($uri, $baseUri);
    // Registered schemas are served by SchemaStorage without a retrieval. Only
    // these exact metadata URIs may reach disk. Never pass a target to a
    // generic retriever, even when its scheme is file or internal.
    if (!in_array($resolved, [
      self::metaSchemaUri(),
      'http://json-schema.org/draft-04/schema',
      'http://json-schema.org/draft-04/schema#',
      'https://json-schema.org/draft-04/schema',
      'https://json-schema.org/draft-04/schema#',
    ], TRUE)) {
      throw new UnsupportedReferenceException('External schema retrieval is disabled.');
    }
    return self::readMetaSchema();
  }

  /**
   * Reads the bundled draft-04 meta-schema.
   *
   * @return object
   *   The decoded meta-schema.
   *
   * @throws \SchemaForms\Exception\UnreadableMetaSchemaException
   *   When the file is missing, unreadable or not a JSON object.
   */
  private static function readMetaSchema(): object {
    $path = self::metaSchemaPath();
    $json = is_readable($path) ? file_get_contents($path) : FALSE;
    $metadata = is_string($json) ? json_decode($json) : NULL;
    if (!is_object($metadata)) {
      throw new UnreadableMetaSchemaException('The bundled meta-schema-draft4.json is missing or is not a JSON object. Reinstall the e0ipso/schema-forms package.');
    }
    return $metadata;
  }

  /**
   * Returns the file URI of the bundled draft-04 meta-schema.
   *
   * The retriever allows this exact URI, so every reference to the bundled
   * file must be built here.
   *
   * @return string
   *   The URI.
   */
  public static function metaSchemaUri(): string {
    return 'file://' . self::metaSchemaPath();
  }

  /**
   * Returns the path of the bundled draft-04 meta-schema.
   *
   * @return string
   *   The path, the only file the retriever reads.
   */
  private static function metaSchemaPath(): string {
    return dirname(__DIR__) . '/meta-schema-draft4.json';
  }

}
