<?php

declare(strict_types=1);

namespace SchemaForms\Drupal;

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * The violation messages that only justinrainbow/json-schema 5.x writes.
 *
 * This serves sites that still install json-schema 5.x, which composer.json
 * allows next to 6.x. Version 5 words some messages differently and names
 * every format violation "format". Delete this class, and its two calls in
 * ConstraintMessages, once composer.json drops the 5.x constraint.
 *
 * @internal
 *
 * @see \SchemaForms\Drupal\ConstraintMessages
 */
final class JsonSchema5ConstraintMessages {

  /**
   * Lists the 6.x constraint names whose messages a 5.x name also writes.
   *
   * @param string $constraint
   *   The constraint name, as json-schema 5.x gives it.
   *
   * @return list<string>
   *   The json-schema 6.x names.
   */
  public static function sharedNames(string $constraint): array {
    return $constraint === 'format'
      ? ['emailFormat', 'urlFormat', 'ipFormat', 'styleHostName', 'dateFormat', 'timeFormat', 'dateTimeFormat']
      : [];
  }

  /**
   * Lists the templates of the messages only json-schema 5.x writes.
   *
   * @param string $constraint
   *   The constraint name.
   *
   * @return list<\Drupal\Core\StringTranslation\TranslatableMarkup>
   *   The templates, with placeholders and no values.
   */
  public static function templates(string $constraint): array {
    return match ($constraint) {
      'minimum' => [new TranslatableMarkup('Must have a minimum value of @minimum')],
      'maximum' => [new TranslatableMarkup('Must have a maximum value of @maximum')],
      'minItems' => [new TranslatableMarkup('There must be a minimum of @minItems items in the array')],
      'maxItems' => [new TranslatableMarkup('There must be a maximum of @maxItems items in the array')],
      default => [],
    };
  }

}
