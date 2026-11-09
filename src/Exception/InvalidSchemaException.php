<?php

declare(strict_types=1);

namespace SchemaForms\Exception;

/**
 * A schema is valid JSON Schema, but the generator cannot represent it.
 *
 * The message names the part of the schema to change, such as a property
 * name that Drupal would read as a render property.
 *
 * @api
 */
final class InvalidSchemaException extends \InvalidArgumentException implements ExceptionInterface {}
