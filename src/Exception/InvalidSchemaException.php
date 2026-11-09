<?php

declare(strict_types=1);

namespace SchemaForms\Exception;

/**
 * A schema is valid JSON Schema, but the generator cannot represent it.
 *
 * @api
 */
final class InvalidSchemaException extends \InvalidArgumentException implements ExceptionInterface {}
