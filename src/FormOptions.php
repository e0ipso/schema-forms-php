<?php

declare(strict_types=1);

namespace SchemaForms;

use SchemaForms\Exception\InvalidOptionException;

/**
 * The options of one transform() call.
 *
 * Pass it as the "options" key of the Shaper Context.
 *
 * @api
 */
final class FormOptions {

  /**
   * Sets the options; each one is optional.
   *
   * @param array<mixed> $uiHints
   *   The UI schema, keyed by property name.
   * @param object|null $formState
   *   The form state, such as Drupal's FormStateInterface. Array widgets keep
   *   their items in it.
   * @param array<mixed>|object $currentInput
   *   Saved values, keyed like the schema, as decoded objects or arrays.
   * @param string|null $namespace
   *   A non-empty name that keeps this container apart from others in the
   *   same form.
   *
   * @throws \SchemaForms\Exception\InvalidOptionException
   *   When the namespace is an empty string.
   */
  public function __construct(
    public readonly array $uiHints = [],
    public readonly ?object $formState = NULL,
    public readonly array|object $currentInput = [],
    public readonly ?string $namespace = NULL,
  ) {
    if ($namespace === '') {
      throw new InvalidOptionException('The "namespace" option must be a non-empty string.');
    }
  }

}
