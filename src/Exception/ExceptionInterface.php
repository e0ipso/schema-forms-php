<?php

declare(strict_types=1);

namespace SchemaForms\Exception;

/**
 * Marks every exception this library throws.
 *
 * Each implementation also extends the exception it replaced, so existing
 * catch blocks keep matching.
 *
 * @api
 */
interface ExceptionInterface extends \Throwable {}
