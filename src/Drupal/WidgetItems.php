<?php

declare(strict_types=1);

namespace SchemaForms\Drupal;

use Drupal\Core\Form\FormStateInterface;

/**
 * The items of a generated array widget, as its form state keeps them.
 *
 * Host code reads it to learn which items a widget holds after its Append
 * and Remove buttons rebuilt the form, and where the widget was built.
 *
 * @api
 */
final readonly class WidgetItems {

  /**
   * Creates the view of a widget's items.
   *
   * @param list<int> $deltas
   *   The item keys in the render array, in form order; a removed item
   *   leaves a gap.
   * @param list<int|string> $arrayParents
   *   The widget's #array_parents when it was last built, or an empty list.
   */
  public function __construct(
    public array $deltas,
    public array $arrayParents,
  ) {}

  /**
   * Reads the items of the widget at a schema path.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state the container was built with.
   * @param list<int|string> $schema_path
   *   The schema path of the array property from the generated root, such
   *   as ['tags'], or ['address', 'lines'] for a property of an object. An
   *   array inside a list item includes the item delta, as in
   *   ['rows', 0, 'tags'].
   * @param string|null $namespace
   *   The namespace option the container was built with, if any.
   *
   * @return self|null
   *   The items, or NULL when no widget keeps state at that path.
   */
  public static function read(FormStateInterface $form_state, array $schema_path, ?string $namespace = NULL): ?self {
    $name = $schema_path === [] ? '' : (string) $schema_path[array_key_last($schema_path)];
    $stored = WidgetState::stored($form_state, WidgetState::keys($schema_path, $name, $namespace));
    if (!is_array($stored)) {
      return NULL;
    }
    $state = WidgetState::fromStorage($stored);
    $array_parents = array_filter($state->arrayParents, static fn (mixed $key): bool => is_int($key) || is_string($key));
    return new self(array_values($state->deltas()), array_values($array_parents));
  }

}
