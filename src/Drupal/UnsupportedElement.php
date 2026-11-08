<?php

declare(strict_types=1);

namespace SchemaForms\Drupal;

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Replaces a control the generator cannot build with an explanation.
 *
 * Emitting nothing is not an option: a property with no control and no
 * message is indistinguishable from a property the schema never declared,
 * which is how an unsupported type stays unnoticed until somebody compares
 * the generated form against the schema by hand.
 *
 * @internal
 */
final class UnsupportedElement {

  /**
   * Turns an element into the "cannot be edited" item.
   *
   * @param array<int|string, mixed> $element
   *   The element.
   * @param string $type
   *   The type that could not be turned into a control.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $reason
   *   The explanation, when it is not the missing element for the type.
   *
   * @return array<int|string, mixed>
   *   The element.
   */
  public static function apply(array $element, string $type, ?TranslatableMarkup $reason = NULL): array {
    $element['#type'] = 'item';
    // 'item' collects a value by default, and the only value it could collect
    // here is the empty string. That string is then type-cast and validated
    // against the property's schema like any other submission, so a property
    // that cannot be rendered would make the whole form unsavable. It has to
    // stay out of the browser's submitted values. Any saved value or schema
    // default is supplied separately before schema validation.
    $element['#input'] = FALSE;
    $element['#markup'] = $reason ?? ValidationMessages::unsupportedType($type);
    $element['#disabled'] = TRUE;
    unset($element['#default_value'], $element['#placeholder']);
    return $element;
  }

}
