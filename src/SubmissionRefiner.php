<?php

declare(strict_types=1);

namespace SchemaForms;

/**
 * Casts submitted form values to the types their JSON Schema declares.
 *
 * Values it cannot cast stay as submitted, for validation to report.
 *
 * @api
 */
final class SubmissionRefiner {

  /**
   * Creates the refiner.
   *
   * @param \SchemaForms\SchemaRegistryInterface $registry
   *   Registers each schema, so its references resolve.
   */
  public function __construct(
    private readonly SchemaRegistryInterface $registry = new RestrictedSchemaRegistry(),
  ) {}

  /**
   * Refines submitted data against a schema.
   *
   * @param mixed $data
   *   The submitted data: arrays, stdClass objects and scalars.
   * @param object $schema
   *   The decoded schema. It is not changed.
   *
   * @return mixed
   *   The data with refined types. A stdClass is refined in place.
   *
   * @throws \SchemaForms\Exception\UnsupportedReferenceException
   *   When the schema references a document the registry does not allow.
   * @throws \SchemaForms\Exception\UnreadableMetaSchemaException
   *   When the schema references the bundled meta-schema and it cannot be
   *   read.
   * @throws \JsonSchema\Exception\RuntimeException
   *   When a reference is not a valid URI.
   * @throws \JsonSchema\Exception\InvalidArgumentException
   *   When a reference inside the schema cannot be resolved.
   */
  public function refine(mixed $data, object $schema): mixed {
    return RecursiveTypeCaster::refine($data, $this->registry->register($schema));
  }

}
