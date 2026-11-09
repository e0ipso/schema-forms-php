<?php

declare(strict_types=1);

namespace SchemaForms\Drupal;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * The item list of a multivalue widget, as kept in form state storage.
 *
 * Form state storage survives the rebuilds the widget buttons trigger. The
 * state is placed at
 * ['prop_storage', '#parents', ...$parents, '#props', $propName], so that a
 * property name never clashes with a segment of another widget's parents.
 * The widgets of a namespaced container keep theirs under
 * ['prop_storage', '#namespaces', $namespace], followed by the same keys.
 * Host code may read the stored array, so neither the keys nor the array
 * change: 'items_indices' lists the item deltas, 'array_parents' records
 * where the widget was last built, and 'next_delta' appears once an item
 * has been removed.
 *
 * @internal
 */
final readonly class WidgetState {

  /**
   * Creates the state.
   *
   * @param array<int>|null $itemsIndices
   *   The item deltas in form order. Removals leave gaps in the keys, as the
   *   stored list always had them. NULL when nothing was stored.
   * @param array<mixed> $arrayParents
   *   The #array_parents of the widget, recorded after it is built.
   * @param int|null $nextDelta
   *   The lowest delta no item has used yet, once an item was removed.
   */
  public function __construct(
    public ?array $itemsIndices,
    public array $arrayParents = [],
    public ?int $nextDelta = NULL,
  ) {}

  /**
   * Lists the form state storage keys of a widget's state.
   *
   * @param array<mixed> $parents
   *   The schema path of the widget.
   * @param string $prop_name
   *   The widget's property name.
   * @param string|null $namespace
   *   The namespace of the generated container, if it has one.
   *
   * @return list<mixed>
   *   The keys.
   */
  public static function keys(array $parents, string $prop_name, ?string $namespace = NULL): array {
    $container = $namespace === NULL ? [] : ['#namespaces', $namespace];
    return ['prop_storage', ...$container, '#parents', ...array_values($parents), '#props', $prop_name];
  }

  /**
   * Lists the storage keys a built widget records.
   *
   * @param array<int|string, mixed> $widget
   *   The widget element.
   *
   * @return list<mixed>
   *   The keys. Values of the wrong type read as absent.
   */
  public static function keysOf(array $widget): array {
    $parents = $widget[RenderKeys::PROP_PARENTS] ?? [];
    $prop_name = $widget[RenderKeys::PROP_NAME] ?? '';
    $namespace = $widget[RenderKeys::NAMESPACE] ?? NULL;
    return self::keys(
      is_array($parents) ? $parents : [],
      is_scalar($prop_name) ? (string) $prop_name : '',
      is_string($namespace) ? $namespace : NULL,
    );
  }

  /**
   * Reads the stored array of a widget.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param list<mixed> $keys
   *   The storage keys of the widget.
   *
   * @return mixed
   *   The stored array, or NULL when nothing is stored.
   */
  public static function stored(FormStateInterface $form_state, array $keys): mixed {
    return NestedArray::getValue($form_state->getStorage(), $keys);
  }

  /**
   * Stores the array of a widget.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param list<mixed> $keys
   *   The storage keys of the widget.
   * @param array<mixed> $state
   *   The array to store.
   */
  public static function store(FormStateInterface $form_state, array $keys, array $state): void {
    NestedArray::setValue($form_state->getStorage(), $keys, $state);
  }

  /**
   * Reads the state of a widget.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param list<mixed> $keys
   *   The storage keys of the widget.
   *
   * @return self
   *   The state.
   */
  public static function load(FormStateInterface $form_state, array $keys): self {
    return self::fromStorage(self::stored($form_state, $keys));
  }

  /**
   * Stores the state of a widget.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param list<mixed> $keys
   *   The storage keys of the widget.
   */
  public function save(FormStateInterface $form_state, array $keys): void {
    self::store($form_state, $keys, $this->toStorage());
  }

  /**
   * Starts the state of a widget showing saved items.
   *
   * @param int $count
   *   The number of saved items.
   *
   * @return self
   *   The state.
   */
  public static function forSavedItems(int $count): self {
    return new self($count > 0 ? range(0, $count - 1) : []);
  }

  /**
   * Reads a stored state.
   *
   * @param mixed $stored
   *   The stored array.
   *
   * @return self
   *   The state. Values of the wrong type read as absent.
   */
  public static function fromStorage(mixed $stored): self {
    $stored = is_array($stored) ? $stored : [];
    $indices = is_array($stored['items_indices'] ?? NULL)
      ? array_filter($stored['items_indices'], 'is_int')
      : NULL;
    $array_parents = is_array($stored['array_parents'] ?? NULL) ? $stored['array_parents'] : [];
    $next_delta = is_int($stored['next_delta'] ?? NULL) ? $stored['next_delta'] : NULL;
    return new self($indices, $array_parents, $next_delta);
  }

  /**
   * Writes the state the way it is stored.
   *
   * @return array<string, mixed>
   *   The stored array.
   */
  public function toStorage(): array {
    return array_filter(
      [
        'items_indices' => $this->itemsIndices,
        'array_parents' => $this->arrayParents,
        'next_delta' => $this->nextDelta,
      ],
      static fn (mixed $value): bool => $value !== NULL
    );
  }

  /**
   * Lists the item deltas.
   *
   * @return array<int>
   *   The deltas in form order.
   */
  public function deltas(): array {
    return $this->itemsIndices ?? [];
  }

  /**
   * Gets the delta of the last item.
   *
   * @return int|null
   *   The delta, or NULL for an empty list. Removals leave gaps, so this is
   *   not the item count.
   */
  public function lastDelta(): ?int {
    $deltas = $this->deltas();
    return $deltas === [] ? NULL : $deltas[array_key_last($deltas)];
  }

  /**
   * Decides whether the widget holds as many items as it may.
   *
   * @param int $cardinality
   *   The widget cardinality.
   *
   * @return bool
   *   TRUE when no further item may be appended. Removals leave gaps, so the
   *   items are counted.
   */
  public function isFull(int $cardinality): bool {
    return $cardinality !== FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED
      && count($this->deltas()) >= $cardinality;
  }

  /**
   * Derives the state with one more item.
   *
   * @return self
   *   The state.
   */
  public function withAppendedItem(): self {
    $delta = $this->nextDelta();
    $deltas = $this->deltas();
    $deltas[] = $delta;
    return new self($deltas, $this->arrayParents, $this->nextDelta === NULL ? NULL : $delta + 1);
  }

  /**
   * Derives the state without an item.
   *
   * @param int $delta
   *   The delta of the removed item, which the list holds.
   *
   * @return self
   *   The state. A removed delta is never given to a new item, which would
   *   otherwise show the removed item's saved value.
   */
  public function withoutItem(int $delta): self {
    $deltas = array_filter($this->deltas(), static fn (int $index): bool => $index !== $delta);
    return new self($deltas, $this->arrayParents, $this->nextDelta());
  }

  /**
   * Derives the state with the widget's final location in the form.
   *
   * @param array<mixed> $array_parents
   *   The #array_parents of the widget.
   *
   * @return self
   *   The state.
   */
  public function withArrayParents(array $array_parents): self {
    return new self($this->itemsIndices, $array_parents, $this->nextDelta);
  }

  /**
   * Picks the delta of the next appended item.
   *
   * @return int
   *   A delta above every delta the widget has used, including removed ones.
   */
  private function nextDelta(): int {
    $deltas = $this->deltas();
    $after_last = $deltas === [] ? 0 : max($deltas) + 1;
    return max($after_last, $this->nextDelta ?? 0);
  }

}
