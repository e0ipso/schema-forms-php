<?php

declare(strict_types=1);

namespace SchemaForms;

/**
 * Registers schemas so that their references resolve without a fetch.
 *
 * The registry decides which documents a schema may reference.
 * RestrictedSchemaRegistry allows the schema itself and the bundled draft-04
 * meta-schema, and fetches nothing. SubmissionRefiner accepts any
 * implementation.
 *
 * @api
 */
interface SchemaRegistryInterface {

  /**
   * Registers a copy of a schema.
   *
   * @param object $schema
   *   The decoded schema. It is not changed.
   *
   * @return \SchemaForms\RegisteredSchema
   *   The registered copy and the factory whose storage holds it.
   *
   * @throws \SchemaForms\Exception\UnsupportedReferenceException
   *   When the schema references a document the registry does not allow.
   * @throws \SchemaForms\Exception\UnreadableMetaSchemaException
   *   When the schema references the bundled meta-schema and it cannot be
   *   read.
   * @throws \JsonSchema\Exception\RuntimeException
   *   When a reference is not a valid URI.
   */
  public function register(object $schema): RegisteredSchema;

}
