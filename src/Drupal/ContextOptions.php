<?php

declare(strict_types=1);

namespace SchemaForms\Drupal;

use SchemaForms\Exception\InvalidOptionException;
use SchemaForms\FormOptions;
use Shaper\Util\Context;

/**
 * Reads the options of a transform() call from its Shaper context.
 *
 * The "options" key holds a FormOptions. Releases before 3.2.0 took the
 * options as four separate keys instead; fromLegacyKeys() still reads them,
 * with a deprecation notice. Delete it and LEGACY_KEYS in 4.0.0.
 *
 * @internal
 */
final class ContextOptions {

  /**
   * The context keys that held the options before FormOptions existed.
   */
  private const LEGACY_KEYS = ['ui_hints', 'form_state', 'current_input', 'namespace'];

  /**
   * Reads the options.
   *
   * @param \Shaper\Util\Context $context
   *   The context of the transform() call.
   *
   * @return \SchemaForms\FormOptions
   *   The "options" key when it holds a FormOptions, otherwise the options
   *   the deprecated keys give.
   *
   * @throws \SchemaForms\Exception\InvalidOptionException
   *   When the deprecated "namespace" key is not a non-empty string.
   */
  public static function read(Context $context): FormOptions {
    $options = $context['options'] ?? NULL;
    return $options instanceof FormOptions ? $options : self::fromLegacyKeys($context);
  }

  /**
   * Builds the options from the deprecated context keys.
   *
   * @param \Shaper\Util\Context $context
   *   The context of the transform() call.
   *
   * @return \SchemaForms\FormOptions
   *   The options. A key with a value of the wrong type reads as absent, as
   *   it always did.
   *
   * @throws \SchemaForms\Exception\InvalidOptionException
   *   When the "namespace" key is not a non-empty string.
   */
  private static function fromLegacyKeys(Context $context): FormOptions {
    $legacy = array_intersect_key($context->getArrayCopy(), array_flip(self::LEGACY_KEYS));
    if ($legacy === []) {
      return new FormOptions();
    }
    // phpcs:ignore Drupal.Semantics.FunctionTriggerError.TriggerErrorSeeUrlFormat -- The project is not on drupal.org.
    @trigger_error('Passing the "ui_hints", "form_state", "current_input" or "namespace" context key to FormGeneratorDrupal::transform() is deprecated in schema_forms:3.2.0 and is removed from schema_forms:4.0.0. Pass a SchemaForms\FormOptions as the "options" context key instead. See https://github.com/e0ipso/schema-forms-php#upgrading', E_USER_DEPRECATED);
    $namespace = $legacy['namespace'] ?? NULL;
    if ($namespace !== NULL && (!is_string($namespace) || $namespace === '')) {
      throw new InvalidOptionException('The "namespace" context key must be a non-empty string.');
    }
    $hints = $legacy['ui_hints'] ?? NULL;
    $form_state = $legacy['form_state'] ?? NULL;
    $input = $legacy['current_input'] ?? NULL;
    return new FormOptions(
      uiHints: is_array($hints) ? $hints : [],
      formState: is_object($form_state) ? $form_state : NULL,
      currentInput: is_array($input) || is_object($input) ? $input : [],
      namespace: $namespace,
    );
  }

}
