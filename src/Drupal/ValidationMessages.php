<?php

declare(strict_types=1);

namespace SchemaForms\Drupal;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Builds the user-facing messages of form generation and validation.
 *
 * Every message is translatable, and every value that comes from the schema,
 * the submission or the JSON Schema validator goes in through a placeholder,
 * so it is escaped exactly once.
 *
 * @internal
 */
final class ValidationMessages {

  /**
   * Explains that a schema reference was refused.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The message.
   */
  public static function unsupportedReference(): TranslatableMarkup {
    return new TranslatableMarkup('The schema contains an unsupported reference. External schema retrieval is disabled.');
  }

  /**
   * Explains that the submission has no JSON representation.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The message.
   */
  public static function unencodableSubmission(): TranslatableMarkup {
    return new TranslatableMarkup('The submitted data could not be encoded as JSON. Please check the submitted values.');
  }

  /**
   * Describes one schema violation.
   *
   * @param string $message
   *   The validator's message.
   * @param string|null $constraint
   *   The name of the violated constraint, if the validator gave one.
   *
   * @return \Drupal\Component\Render\MarkupInterface|string
   *   The description. A plain string is escaped by the message it goes in.
   */
  public static function violation(string $message, ?string $constraint): MarkupInterface|string {
    if ($constraint === NULL) {
      return $message;
    }
    // The quotes are part of the escaped value, so the markup stays what it
    // was when the whole description was one escaped string.
    return new TranslatableMarkup('@message [JSON Schema violation of @constraint]', [
      '@message' => $message,
      '@constraint' => '"' . $constraint . '"',
    ]);
  }

  /**
   * Builds the error for the element a violation belongs to.
   *
   * @param mixed $title
   *   The element title.
   * @param \Drupal\Component\Render\MarkupInterface|string $violation
   *   The violation description.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The message.
   */
  public static function elementViolation(mixed $title, MarkupInterface|string $violation): TranslatableMarkup {
    return new TranslatableMarkup('%element: @message', [
      '%element' => $title,
      '@message' => $violation,
    ]);
  }

  /**
   * Builds the error for violations no element can show.
   *
   * @param list<\Drupal\Component\Render\MarkupInterface|string> $violations
   *   The violation descriptions.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The message.
   */
  public static function invalidData(array $violations): TranslatableMarkup {
    // One placeholder per violation escapes each plain description once and
    // leaves the translatable ones alone, without rendering anything yet.
    $placeholders = array_map(static fn (int $index): string => '@violation' . $index, array_keys($violations));
    $list = new FormattableMarkup(implode(', ', $placeholders), array_combine($placeholders, $violations));
    return new TranslatableMarkup(
      '<p>Invalid data, please make sure all data is valid according to the schema. Schema validation returned the following errors: @full_message</p>',
      ['@full_message' => $list]
    );
  }

}
