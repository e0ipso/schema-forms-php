<?php

declare(strict_types=1);

namespace SchemaForms\Exception;

/**
 * Marks every exception this library throws.
 *
 * Catch this interface to handle any failure from the library at once. Each
 * implementation also extends the exception it replaced, so existing catch
 * blocks keep matching.
 *
 * @api
 */
interface ExceptionInterface extends \Throwable {}
