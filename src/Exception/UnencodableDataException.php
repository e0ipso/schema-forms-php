<?php

declare(strict_types=1);

namespace SchemaForms\Exception;

/**
 * Data cannot be encoded as JSON.
 *
 * Invalid UTF-8, recursive structures, INF and NAN have no JSON encoding.
 * The previous exception is the \JsonException that names the cause.
 */
final class UnencodableDataException extends \InvalidArgumentException implements ExceptionInterface {}
