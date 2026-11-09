<?php

declare(strict_types=1);

namespace SchemaForms;

use JsonSchema\Constraints\Factory;
use JsonSchema\UriRetrieverInterface;
use SchemaForms\Exception\UnreadableMetaSchemaException;
use SchemaForms\Exception\UnsupportedReferenceException;

/**
 * Retrieves the bundled draft-04 meta-schema and refuses every other URI.
 *
 * @internal
 */
final class SchemaReferenceResolver implements UriRetrieverInterface {

  /**
   * The storage key shared by generation and submission validation.
   */
  public const SCHEMA_URI = 'internal:/schema-with-refs';

  /**
   * The draft-04 identifiers the bundled file answers, never the network.
   */
  private const DRAFT_04_URIS = [
    'http://json-schema.org/draft-04/schema',
    'http://json-schema.org/draft-04/schema#',
    'https://json-schema.org/draft-04/schema',
    'https://json-schema.org/draft-04/schema#',
  ];

  // phpcs:disable Drupal.Commenting.Deprecated.DeprecatedWrongSeeUrlFormat, Drupal.Semantics.FunctionTriggerError.TriggerErrorSeeUrlFormat -- The project is not on drupal.org.

  /**
   * Creates a factory with the restricted retriever.
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
   *
   * @deprecated in schema_forms:3.2.0 and is removed from schema_forms:4.0.0.
   *   Use RestrictedSchemaRegistry::register() for a schema, or
   *   RestrictedSchemaRegistry::factory() without one.
   *
   * @see https://github.com/e0ipso/schema-forms-php#upgrading
   */
  public static function createFactory(?object $schema = NULL): Factory {
    @trigger_error('SchemaReferenceResolver::createFactory() is deprecated in schema_forms:3.2.0 and is removed from schema_forms:4.0.0. Use RestrictedSchemaRegistry::register() or RestrictedSchemaRegistry::factory() instead. See https://github.com/e0ipso/schema-forms-php#upgrading', E_USER_DEPRECATED);
    $registry = new RestrictedSchemaRegistry();
    return $schema === NULL ? $registry->factory() : $registry->register($schema)->factory;
  }

  /**
   * Returns the schema registered by createFactory(), references expanded.
   *
   * @param \JsonSchema\Constraints\Factory $factory
   *   A factory returned by createFactory() for a schema.
   *
   * @return object
   *   The registered copy of the schema.
   *
   * @deprecated in schema_forms:3.2.0 and is removed from schema_forms:4.0.0.
   *   Use the schema property of RestrictedSchemaRegistry::register().
   *
   * @see https://github.com/e0ipso/schema-forms-php#upgrading
   */
  public static function registeredSchema(Factory $factory): object {
    @trigger_error('SchemaReferenceResolver::registeredSchema() is deprecated in schema_forms:3.2.0 and is removed from schema_forms:4.0.0. Use the schema property of RestrictedSchemaRegistry::register() instead. See https://github.com/e0ipso/schema-forms-php#upgrading', E_USER_DEPRECATED);
    $schema = $factory->getSchemaStorage()->getSchema(self::SCHEMA_URI);
    // createFactory() registers an object, never a boolean schema.
    assert(is_object($schema));
    return $schema;
  }

  // phpcs:enable

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
    if ($resolved !== self::metaSchemaUri() && !in_array($resolved, self::DRAFT_04_URIS, TRUE)) {
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
   * The retriever allows this exact URI, so every reference to the file is
   * built here.
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
