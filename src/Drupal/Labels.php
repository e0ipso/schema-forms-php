<?php

declare(strict_types=1);

namespace SchemaForms\Drupal;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Render\Markup;

/**
 * Builds the labels of generated controls and options.
 *
 * @internal
 */
final class Labels {

  /**
   * Turns a label from the schema or the UI hints into safe markup.
   *
   * Labels may carry inline HTML such as emphasis and links, so they are
   * filtered rather than escaped.
   *
   * @param mixed $label
   *   The label.
   *
   * @return \Drupal\Component\Render\MarkupInterface|string
   *   The filtered label; an empty label stays an empty string.
   */
  public static function markup(mixed $label): MarkupInterface|string {
    return Markup::create(Xss::filter(is_scalar($label) || $label instanceof \Stringable ? (string) $label : ''));
  }

  /**
   * Turns a machine name into a human readable name.
   *
   * @param string $machine_name
   *   The machine name.
   *
   * @return string
   *   The name with hyphens and underscores as spaces, words capitalized.
   */
  public static function humanName(string $machine_name): string {
    return ucwords(strtr($machine_name, ['_' => ' ', '-' => ' ']));
  }

}
