<?php

declare(strict_types=1);

namespace SchemaForms\Drupal;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\TranslatableMarkup;

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
    return Markup::create(Xss::filter((string) self::placeholder($label)));
  }

  /**
   * Gives the hidden title of a multivalue item.
   *
   * The item control and the removal announcement both use it, so a screen
   * reader hears the same name for the item in both places.
   *
   * @param mixed $title
   *   The title of the widget.
   * @param int $number
   *   The position of the item among the rendered items, from 1.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The title.
   */
  public static function itemTitle(mixed $title, int $number): TranslatableMarkup {
    return new TranslatableMarkup('@title (value @number)', [
      '@title' => self::placeholder($title),
      '@number' => $number,
    ]);
  }

  /**
   * Gives the accessible name of a multivalue item's Remove button.
   *
   * The name goes into the aria-label and title attributes. Drupal's
   * Attribute turns markup into plain text there, so a title with markup
   * is read as its text.
   *
   * @param mixed $title
   *   The title of the widget.
   * @param int $number
   *   The position of the item among the rendered items, from 1.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The name.
   */
  public static function removeButtonName(mixed $title, int $number): TranslatableMarkup {
    return new TranslatableMarkup('Remove @title (value @number)', [
      '@title' => self::placeholder($title),
      '@number' => $number,
    ]);
  }

  /**
   * Gives a value that a translation placeholder can hold.
   *
   * A title in a render array can hold anything, and a placeholder fails on
   * an array or an object that has no string form.
   *
   * @param mixed $value
   *   The value.
   *
   * @return bool|float|int|string|\Stringable
   *   The value, or an empty string when it has no string form.
   */
  public static function placeholder(mixed $value): bool|float|int|string|\Stringable {
    return is_scalar($value) || $value instanceof \Stringable ? $value : '';
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
