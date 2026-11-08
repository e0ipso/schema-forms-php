<?php

declare(strict_types=1);

namespace SchemaForms\Drupal;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;

/**
 * Keeps the state of multivalue widgets in form state storage.
 *
 * The state must survive the rebuilds the widget buttons trigger, and form
 * state storage is what Drupal carries from one build to the next.
 *
 * @internal
 *
 * @see \SchemaForms\Drupal\WidgetLocation
 */
final readonly class WidgetStateStorage {

  /**
   * Creates the storage.
   *
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The form state whose storage holds the widget states.
   */
  public function __construct(
    private FormStateInterface $formState,
  ) {}

  /**
   * Reads the stored array of a widget.
   *
   * @param \SchemaForms\Drupal\WidgetLocation $location
   *   Where the widget keeps its state.
   *
   * @return mixed
   *   The stored array, or NULL when nothing is stored.
   */
  public function get(WidgetLocation $location): mixed {
    return NestedArray::getValue($this->formState->getStorage(), $location->storageKeys());
  }

  /**
   * Stores the array of a widget.
   *
   * @param \SchemaForms\Drupal\WidgetLocation $location
   *   Where the widget keeps its state.
   * @param array<mixed> $state
   *   The array to store.
   */
  public function set(WidgetLocation $location, array $state): void {
    NestedArray::setValue($this->formState->getStorage(), $location->storageKeys(), $state);
  }

  /**
   * Reads the typed state of a widget.
   *
   * @param \SchemaForms\Drupal\WidgetLocation $location
   *   Where the widget keeps its state.
   *
   * @return \SchemaForms\Drupal\WidgetState
   *   The state.
   */
  public function load(WidgetLocation $location): WidgetState {
    return WidgetState::fromStorage($this->get($location));
  }

  /**
   * Stores the typed state of a widget.
   *
   * @param \SchemaForms\Drupal\WidgetLocation $location
   *   Where the widget keeps its state.
   * @param \SchemaForms\Drupal\WidgetState $state
   *   The state.
   */
  public function save(WidgetLocation $location, WidgetState $state): void {
    $this->set($location, $state->toStorage());
  }

}
