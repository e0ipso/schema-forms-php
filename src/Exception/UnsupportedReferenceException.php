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
 * It extends UnresolvableJsonPointerException because 3.1.1 and earlier threw
 * that exception for these references, and catch blocks written for it must
 * keep matching. The cost is that a catch for a malformed pointer also
 * catches this refusal; catch UnsupportedReferenceException first to tell
 * the two apart. Changing the parent would break those catch blocks, so it
 * can only change in a major release.
 *
 * @see \JsonSchema\Exception\UnresolvableJsonPointerException
 *
 * @api
 */
final class UnsupportedReferenceException extends UnresolvableJsonPointerException implements ExceptionInterface {}
