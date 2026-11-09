<?php

declare(strict_types=1);

namespace SchemaForms\Drupal;

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Makes the messages of common JSON Schema violations translatable.
 *
 * Each template spells one validator message with placeholders. When it
 * matches the message, the matched text becomes the placeholder values, so
 * the English output is the validator's message byte for byte. Matching the
 * message, rather than rebuilding it from the error's parameters, holds for
 * both json-schema majors, which format and report values differently.
 *
 * @internal
 */
final class ConstraintMessages {

  /**
   * The format constraints json-schema 6 names; 5.x calls each one "format".
   */
  private const FORMATS = [
    'emailFormat', 'urlFormat', 'ipFormat', 'styleHostName', 'dateFormat',
    'timeFormat', 'dateTimeFormat',
  ];

  /**
   * Gives the translatable form of a validator message.
   *
   * @param string $constraint
   *   The name of the violated constraint.
   * @param string $message
   *   The validator's message.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|null
   *   The translatable message, or NULL when no template matches.
   */
  public static function translatable(string $constraint, string $message): ?TranslatableMarkup {
    $names = $constraint === 'format' ? self::FORMATS : [$constraint];
    $templates = array_merge(...array_map([self::class, 'templates'], $names));
    $matches = array_filter(array_map(
      static fn (TranslatableMarkup $template): ?TranslatableMarkup => self::fromMessage($message, $template),
      $templates
    ));
    $match = reset($matches);
    return $match instanceof TranslatableMarkup ? $match : NULL;
  }

  /**
   * Lists the templates of a constraint.
   *
   * Each template is a literal, so that translation extractors find it. The
   * second template of a constraint is the wording of json-schema 5.x; delete
   * those, and the "format" mapping, once composer.json drops 5.x.
   *
   * @param string $constraint
   *   The constraint name.
   *
   * @return list<\Drupal\Core\StringTranslation\TranslatableMarkup>
   *   The templates, with placeholders and no values.
   */
  private static function templates(string $constraint): array {
    return match ($constraint) {
      'required' => [new TranslatableMarkup('The property @property is required')],
      'type' => [new TranslatableMarkup('@found value found, but @expected is required')],
      'enum' => [new TranslatableMarkup('Does not have a value in the enumeration @enum')],
      'minLength' => [new TranslatableMarkup('Must be at least @minLength characters long')],
      'maxLength' => [new TranslatableMarkup('Must be at most @maxLength characters long')],
      'minimum' => [
        new TranslatableMarkup('Must have a minimum value greater than or equal to @minimum'),
        new TranslatableMarkup('Must have a minimum value of @minimum'),
      ],
      'maximum' => [
        new TranslatableMarkup('Must have a maximum value less than or equal to @maximum'),
        new TranslatableMarkup('Must have a maximum value of @maximum'),
      ],
      'pattern' => [new TranslatableMarkup('Does not match the regex pattern @pattern')],
      'minItems' => [
        new TranslatableMarkup('There must be a minimum of @minItems items in the array, @found found'),
        new TranslatableMarkup('There must be a minimum of @minItems items in the array'),
      ],
      'maxItems' => [
        new TranslatableMarkup('There must be a maximum of @maxItems items in the array, @found found'),
        new TranslatableMarkup('There must be a maximum of @maxItems items in the array'),
      ],
      'emailFormat' => [new TranslatableMarkup('Invalid email')],
      'urlFormat' => [new TranslatableMarkup('Invalid URL format')],
      'ipFormat' => [new TranslatableMarkup('Invalid IP address')],
      'styleHostName' => [new TranslatableMarkup('Invalid hostname')],
      'dateFormat' => [new TranslatableMarkup('Invalid date @date, expected format YYYY-MM-DD')],
      'timeFormat' => [new TranslatableMarkup('Invalid time @time, expected format hh:mm:ss')],
      'dateTimeFormat' => [new TranslatableMarkup('Invalid date-time @dateTime, expected format YYYY-MM-DDThh:mm:ssZ or YYYY-MM-DDThh:mm:ss+hh:mm')],
      default => [],
    };
  }

  /**
   * Reads a message back into a template's placeholder values.
   *
   * @param string $message
   *   The validator's message.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $template
   *   The template, without values.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|null
   *   The template with the message's values, or NULL on no match.
   */
  private static function fromMessage(string $message, TranslatableMarkup $template): ?TranslatableMarkup {
    $english = $template->getUntranslatedString();
    preg_match_all('/@(\w+)/', $english, $placeholders);
    $pattern = (string) preg_replace('/@(\w+)/', '(?<$1>.+?)', preg_quote($english, '/'));
    if (!preg_match('/^' . $pattern . '$/s', $message, $values)) {
      return NULL;
    }
    $names = $placeholders[1];
    $arguments = array_combine(
      array_map(static fn (string $name): string => '@' . $name, $names),
      array_map(static fn (string $name): string => $values[$name], $names)
    );
    // phpcs:ignore Drupal.Semantics.FunctionT.NotLiteralString -- templates() holds the literal.
    return new TranslatableMarkup($english, $arguments);
  }

}
