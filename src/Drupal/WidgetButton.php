<?php

declare(strict_types=1);

namespace SchemaForms\Drupal;

/**
 * A multivalue button found in a generated tree.
 *
 * @internal
 */
final readonly class WidgetButton {

  /**
   * Creates the button reference.
   *
   * @param list<int|string> $path
   *   The keys from the generated root to the button.
   * @param list<string> $parents
   *   The schema path of the widget, or of the item for a Remove button.
   * @param string $name
   *   The name the button was built with.
   * @param bool $removes
   *   TRUE for a Remove button, FALSE for an Append button.
   */
  public function __construct(
    public array $path,
    public array $parents,
    public string $name,
    public bool $removes,
  ) {}

}
