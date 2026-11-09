<?php

declare(strict_types=1);

namespace SchemaForms\Exception;

use JsonSchema\Exception\UnresolvableJsonPointerException;

/**
 * A schema reference points outside the registered documents.
 *
 * It extends UnresolvableJsonPointerException because 3.1.1 threw that for
 * these references, and catch blocks written for it must keep matching.
 *
 * @api
 */
final class UnsupportedReferenceException extends UnresolvableJsonPointerException implements ExceptionInterface {}
