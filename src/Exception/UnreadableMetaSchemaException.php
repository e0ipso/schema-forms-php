<?php

declare(strict_types=1);

namespace SchemaForms\Exception;

/**
 * The bundled draft-04 meta-schema cannot be read.
 *
 * The installation is incomplete: reinstall the package.
 *
 * @api
 */
final class UnreadableMetaSchemaException extends \RuntimeException implements ExceptionInterface {}
