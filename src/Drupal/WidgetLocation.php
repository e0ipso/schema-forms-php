<?php

declare(strict_types=1);

namespace SchemaForms\Drupal;

/**
 * Where a multivalue widget keeps its state in form state storage.
 *
 * The state is placed at
 * ['prop_storage', '#parents', ...$parents, '#props', $propName], so that a
 * property name never clashes with a segment of another widget's parents.
 * The widgets of a namespaced container keep theirs under
 * ['prop_storage', '#namespaces', $namespace], followed by the same keys.
 * Host code may read the state there, so the keys never change.
 *
 * @internal
 */
final readonly class WidgetLocation {

  /**
   * Creates the location.
   *
   * @param array<mixed> $parents
   *   The schema path of the widget.
   * @param string $propName
   *   The widget's property name.
   * @param string|null $namespace
   *   The namespace of the generated container, if it has one.
   */
  public function __construct(
    public array $parents,
    public string $propName,
    public ?string $namespace = NULL,
  ) {}

  /**
   * Reads the location a built widget records.
   *
   * @param array<int|string, mixed> $widget
   *   The widget element.
   *
   * @return self
   *   The location. Values of the wrong type read as absent.
   */
  public static function ofWidget(array $widget): self {
    $parents = $widget[RenderKeys::PROP_PARENTS] ?? [];
    $prop_name = $widget[RenderKeys::PROP_NAME] ?? '';
    $namespace = $widget[RenderKeys::NAMESPACE] ?? NULL;
    return new self(
      is_array($parents) ? $parents : [],
      is_scalar($prop_name) ? (string) $prop_name : '',
      is_string($namespace) ? $namespace : NULL,
    );
  }

  /**
   * Lists the keys into form state storage.
   *
   * @return list<mixed>
   *   The keys.
   */
  public function storageKeys(): array {
    $container = $this->namespace === NULL ? [] : ['#namespaces', $this->namespace];
    return ['prop_storage', ...$container, '#parents', ...array_values($this->parents), '#props', $this->propName];
  }

}
