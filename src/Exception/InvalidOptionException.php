<?php

declare(strict_types=1);

namespace SchemaForms\Exception;

/**
 * An option passed to the library has a value it does not accept.
 *
 * The message names the option and the values it accepts.
 *
 * @api
 */
final class InvalidOptionException extends \InvalidArgumentException implements ExceptionInterface {}
