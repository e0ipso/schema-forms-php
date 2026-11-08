<?php

declare(strict_types=1);

namespace SchemaForms\Drupal;

use Drupal\Core\Form\FormStateInterface;

/**
 * What the generator knows while it builds the control for one schema.
 *
 * @internal
 */
final readonly class FieldContext {

  /**
   * Creates the context.
   *
   * @param \stdClass $schema
   *   The schema the control is built for.
   * @param string $name
   *   The property name, or the item delta. Used for fallback labels.
   * @param list<int|string> $parents
   *   The schema path from the generated root to the control.
   * @param array<mixed> $hints
   *   The UI hints of the control.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The form state, which keeps the item lists of multivalue widgets.
   * @param mixed $currentInput
   *   The saved value, or the normalized default of a compound control.
   * @param bool $hasCurrentInput
   *   Whether a value was saved, including an explicit NULL.
   * @param bool $enabled
   *   FALSE when the control or one of its ancestors is disabled.
   * @param string|null $namespace
   *   The namespace of the generated container, if the caller gave one.
   */
  public function __construct(
    public \stdClass $schema,
    public string $name,
    public array $parents,
    public array $hints,
    public FormStateInterface $formState,
    public mixed $currentInput,
    public bool $hasCurrentInput,
    public bool $enabled = TRUE,
    public ?string $namespace = NULL,
  ) {}

  /**
   * Derives the context of a sub-property.
   *
   * @param int|string $key
   *   The property name, as the property list keys it.
   * @param \stdClass $schema
   *   The property schema.
   * @param array<mixed> $hints
   *   The property's UI hints.
   *
   * @return self
   *   The context.
   */
  public function child(int|string $key, \stdClass $schema, array $hints): self {
    $input = is_array($this->currentInput) ? $this->currentInput : [];
    // Inside a saved NULL, every sub-property is saved NULL too.
    $inside_saved_null = $this->hasCurrentInput && $this->currentInput === NULL;
    return new self(
      $schema,
      (string) $key,
      [...$this->parents, $key],
      $hints,
      $this->formState,
      $input[$key] ?? NULL,
      $inside_saved_null || array_key_exists($key, $input),
      $this->enabled,
      $this->namespace,
    );
  }

  /**
   * Derives the context of a multivalue item.
   *
   * @param int $delta
   *   The item delta.
   * @param \stdClass $schema
   *   The item schema.
   *
   * @return self
   *   The context. Items share the hints of their list.
   */
  public function item(int $delta, \stdClass $schema): self {
    $input = is_array($this->currentInput) ? $this->currentInput : [];
    return new self(
      $schema,
      (string) $delta,
      [...$this->parents, $delta],
      $this->hints,
      $this->formState,
      $input[$delta] ?? NULL,
      array_key_exists($delta, $input),
      $this->enabled,
      $this->namespace,
    );
  }

  /**
   * Derives a context with another value to show.
   *
   * @param mixed $input
   *   The value. Whether a value was saved does not change.
   *
   * @return self
   *   The context.
   */
  public function withCurrentInput(mixed $input): self {
    return new self($this->schema, $this->name, $this->parents, $this->hints, $this->formState, $input, $this->hasCurrentInput, $this->enabled, $this->namespace);
  }

  /**
   * Derives a context that takes the control's own 'ui:enabled' into account.
   *
   * @return self
   *   The context.
   */
  public function withOwnEnabledHint(): self {
    $enabled = $this->enabled && (bool) ($this->hints['ui:enabled'] ?? TRUE);
    return new self($this->schema, $this->name, $this->parents, $this->hints, $this->formState, $this->currentInput, $this->hasCurrentInput, $enabled, $this->namespace);
  }

}
