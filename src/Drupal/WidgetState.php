<?php

declare(strict_types=1);

namespace SchemaForms\Drupal;

use Drupal\Core\Field\FieldStorageDefinitionInterface;

/**
 * The item list of a multivalue widget, as kept in form state storage.
 *
 * Host code may read the stored array, so it keeps its keys: 'items_indices'
 * lists the item deltas, 'array_parents' records where the widget was last
 * built, and 'next_delta' appears once an item has been removed.
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
