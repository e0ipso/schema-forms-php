<?php

declare(strict_types=1);

namespace SchemaForms\Exception;

/**
 * The bundled draft-04 meta-schema cannot be read.
 *
 * The package ships meta-schema-draft4.json and validates every schema
 * against it. The file is missing, unreadable or not a JSON object, so the
 * installation is incomplete: reinstall the package.
 *
 * @api
 */
final class UnreadableMetaSchemaException extends \RuntimeException implements ExceptionInterface {}
