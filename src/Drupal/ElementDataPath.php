<?php

declare(strict_types=1);

namespace SchemaForms\Drupal;

/**
 * A generated element and the path of its value in the cleaned submission.
 *
 * @internal
 */
final readonly class ElementDataPath {

  /**
   * Creates the pair.
   *
   * @param list<int|string> $path
   *   The keys from the root value to this element's value. Multivalue items
   *   count by their position in the stored list, not by their form delta.
   * @param array<int|string, mixed> $element
   *   The element.
   */
  public function __construct(
    public array $path,
    public array $element,
  ) {}

}
