<?php

declare(strict_types=1);

namespace SchemaForms\Exception;

/**
 * Code tried to replace a validator that the library builds itself.
 *
 * Another validator could fetch external files and URLs.
 *
 * @internal
 */
final class ValidatorReplacementException extends \LogicException implements ExceptionInterface {}
