<?php

declare(strict_types=1);

namespace SchemaForms\Exception;

/**
 * Code tried to replace a validator that the library builds itself.
 *
 * Such a validator only resolves references inside registered documents.
 * Accepting another validator would let it fetch external files and URLs.
 *
 * @internal
 */
final class ValidatorReplacementException extends \LogicException implements ExceptionInterface {}
