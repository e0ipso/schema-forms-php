<?php

declare(strict_types=1);

namespace SchemaForms;

use JsonSchema\Constraints\Factory;

/**
 * A registered schema and the factory whose storage holds it.
 *
 * Keeping the two together means a caller cannot pair a schema with the
 * wrong factory.
 *
 * @api
 */
final class RegisteredSchema {

  /**
   * Pairs a registered schema with its factory.
   *
   * @param object $schema
   *   The schema as the factory's storage holds it, references expanded.
   * @param \JsonSchema\Constraints\Factory $factory
   *   The factory whose storage resolves every reference in the schema.
   */
  public function __construct(
    public readonly object $schema,
    public readonly Factory $factory,
  ) {}

}
