<?php

declare(strict_types=1);

namespace SchemaForms;

use JsonSchema\Constraints\Factory;
use JsonSchema\SchemaStorage;

/**
 * Registers schemas whose references stay inside registered documents.
 *
 * A reference may point inside the schema and to the bundled draft-04
 * meta-schema. Every other target is refused when the schema is registered,
 * including targets in branches that validation never reaches. No file or
 * URL is ever fetched.
 *
 * @api
 */
final class RestrictedSchemaRegistry implements SchemaRegistryInterface {

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function register(object $schema): RegisteredSchema {
    $factory = $this->factory();
    $storage = $factory->getSchemaStorage();
    // Storage rewrites references in place, so register a copy and leave
    // the caller's schema as it was.
    $copy = SchemaCopier::copy($schema);
    // Older releases treat a single-slash internal URI as a local file base.
    // Expand local references with a URI both supported majors recognize,
    // then keep the original storage key for explicitly registered refs.
    $storage->addSchema('internal://schema-with-refs/', $copy);
    $storage->addSchema(SchemaReferenceResolver::SCHEMA_URI, $copy);
    ReferenceChecker::check($copy, $storage);
    $registered = $storage->getSchema(SchemaReferenceResolver::SCHEMA_URI);
    // The storage holds the copy of an object, never a boolean schema.
    assert(is_object($registered));
    return new RegisteredSchema($registered, $factory);
  }

  /**
   * Creates a factory that resolves no reference but the meta-schema.
   *
   * Use it to validate against the bundled draft-04 meta-schema, or to
   * register more documents in its storage.
   *
   * @return \JsonSchema\Constraints\Factory
   *   A factory in the normal check mode, with nothing registered.
   */
  public function factory(): Factory {
    $retriever = new SchemaReferenceResolver();
    return new Factory(new SchemaStorage($retriever), $retriever);
  }

}
