<?php

declare(strict_types=1);

namespace SchemaForms\Exception;

use JsonSchema\Exception\UnresolvableJsonPointerException;

/**
 * A schema reference points outside the registered documents.
 *
 * External files and URLs are never retrieved. Inline the referenced schema,
 * or point the reference at a definition inside the same document.
 *
 * @api
 */
final class UnsupportedReferenceException extends UnresolvableJsonPointerException implements ExceptionInterface {}
