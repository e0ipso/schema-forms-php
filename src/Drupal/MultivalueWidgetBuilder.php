<?php

declare(strict_types=1);

namespace SchemaForms\Drupal;

use Drupal\Component\Utility\Html;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Render\Element\Details;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Builds the widget of an array with one item schema.
 *
 * The widget is an open details element with one container per item, each
 * holding the item control and a Remove button, followed by an Append
 * button. The item list lives in form state storage, so it survives the
 * rebuilds the buttons trigger.
 *
 * @internal
 *
 * @see \SchemaForms\Drupal\MultivalueWidget
 */
final class MultivalueWidgetBuilder {

  /**
   * The position of the Remove button inside its item.
   */
  private const REMOVE_BUTTON_WEIGHT = 101;

  /**
   * The inline style that puts the Remove button on the item's corner.
   */
  private const REMOVE_BUTTON_STYLE = 'opacity: 0.9;display: block;position: absolute;top: -10px;left: -10px;margin: 0;overflow: hidden;width: 20px;height: 20px;padding: 0;border-radius: 15px;';

  /**
   * Builds the widget.
   *
   * @param array<int|string, mixed> $element
   *   The scaffolded element.
   * @param \SchemaForms\Drupal\FieldContext $context
   *   The field context of the array.
   * @param \stdClass $items
   *   The item schema.
   * @param \SchemaForms\Drupal\FieldBuilder $fields
   *   Builds the item controls.
   *
   * @return array<int|string, mixed>
   *   The widget.
   */
  public function build(array $element, FieldContext $context, \stdClass $items, FieldBuilder $fields): array {
    $state = $this->state($context);
    $cardinality = self::cardinality($context->schema);
    $id_prefix = implode('-', $context->parents);
    $wrapper_id = Html::getUniqueId($id_prefix . '-add-more-wrapper');
    $after_build = is_array($element['#after_build'] ?? NULL) ? $element['#after_build'] : [];
    $element = [
      '#type' => 'details',
      '#open' => TRUE,
      // Keep the details element's default processing before setting the
      // validation path.
      '#process' => [
        [Details::class, 'processGroup'],
        [Details::class, 'processAjaxForm'],
        [FormGeneratorDrupal::class, 'multiValueProcess'],
      ],
      '#after_build' => [...$after_build, [FormGeneratorDrupal::class, 'multiValueAfterBuild']],
      RenderKeys::CARDINALITY => $cardinality,
      // Saved items beyond maxItems stay, so that validation reports them.
      RenderKeys::MAX_DELTA => $state->lastDelta(),
      '#prefix' => '<div id="' . $wrapper_id . '">',
      '#suffix' => '</div>',
    ] + $element;
    if ($context->namespace !== NULL) {
      // The callbacks find the widget state through it.
      $element[RenderKeys::NAMESPACE] = $context->namespace;
    }
    foreach ($state->deltas() as $delta) {
      $element[$delta] = $this->item($element, $context->item($delta, $items), $fields, $wrapper_id);
    }
    $element[RenderKeys::APPEND_BUTTON] = $this->appendButton($context, $wrapper_id);
    if ($state->isFull($cardinality)) {
      // An inaccessible button cannot trigger a submission either.
      $element[RenderKeys::APPEND_BUTTON]['#access'] = FALSE;
    }
    return $element;
  }

  /**
   * Gets the widget state, starting it from the saved items on first build.
   *
   * @param \SchemaForms\Drupal\FieldContext $context
   *   The field context of the array.
   *
   * @return \SchemaForms\Drupal\WidgetState
   *   The state.
   */
  private function state(FieldContext $context): WidgetState {
    $stored = WidgetStateStorage::get($context->formState, $context->parents, $context->name, $context->namespace);
    if (!empty($stored)) {
      return WidgetState::fromStorage($stored);
    }
    $count = is_array($context->currentInput) ? count($context->currentInput) : 0;
    $state = WidgetState::forSavedItems($count);
    WidgetStateStorage::set($context->formState, $context->parents, $context->name, $state->toStorage(), $context->namespace);
    return $state;
  }

  /**
   * Builds the container of one item.
   *
   * @param array<int|string, mixed> $widget
   *   The widget so far.
   * @param \SchemaForms\Drupal\FieldContext $context
   *   The field context of the item.
   * @param \SchemaForms\Drupal\FieldBuilder $fields
   *   Builds the item control.
   * @param string $wrapper_id
   *   The HTML ID of the widget wrapper.
   *
   * @return array<int|string, mixed>
   *   The item container.
   */
  private function item(array $widget, FieldContext $context, FieldBuilder $fields, string $wrapper_id): array {
    // The wrapping details element shows the title and description, even
    // when maxItems is 1; each item gets an invisible numbered title.
    $title = $widget['#title'] ?? '';
    $control = [
      '#title' => new TranslatableMarkup('@title (value @number)', [
        '@title' => is_scalar($title) || $title instanceof \Stringable ? $title : '',
        '@number' => (int) $context->name + 1,
      ]),
      '#title_display' => 'invisible',
      '#description' => '',
    ] + $fields->build($context);
    // The ID is taken after the item control is built, so nested widgets
    // claim their IDs first and every ID stays as it always was.
    $container_id = Html::getUniqueId(sprintf('%s-%d-container', implode('-', array_slice($context->parents, 0, -1)), $context->name));
    return [
      '#type' => 'container',
      '#prefix' => '<div id="' . $container_id . '">',
      '#wrapper_id' => $container_id,
      '#suffix' => '</div>',
      '#attributes' => ['style' => 'position: relative;'],
      RenderKeys::ITEM => $control,
      RenderKeys::REMOVE_BUTTON => $this->removeButton($context, $wrapper_id),
    ];
  }

  /**
   * Builds the Remove button of an item.
   *
   * @param \SchemaForms\Drupal\FieldContext $context
   *   The field context of the item.
   * @param string $wrapper_id
   *   The HTML ID of the widget wrapper, which the response replaces.
   *
   * @return array<string, mixed>
   *   The button.
   */
  private function removeButton(FieldContext $context, string $wrapper_id): array {
    return [
      '#delta' => (int) $context->name,
      '#type' => 'submit',
      '#name' => ButtonNames::remove($context->parents, $context->namespace),
      '#value' => new TranslatableMarkup('⨯'),
      '#validate' => [],
      RenderKeys::PROP_PARENTS => $context->parents,
      '#attributes' => [
        'class' => ['field-remove-one-submit'],
        'style' => self::REMOVE_BUTTON_STYLE,
        'title' => new TranslatableMarkup('Remove item'),
      ],
      '#limit_validation_errors' => [],
      '#submit' => [[FormGeneratorDrupal::class, 'removeOneSubmit']],
      '#ajax' => [
        'callback' => [FormGeneratorDrupal::class, 'removeOneAjax'],
        'wrapper' => $wrapper_id,
        'effect' => 'fade',
      ],
      '#weight' => self::REMOVE_BUTTON_WEIGHT,
    ];
  }

  /**
   * Builds the Append button of the widget.
   *
   * @param \SchemaForms\Drupal\FieldContext $context
   *   The field context of the array.
   * @param string $wrapper_id
   *   The HTML ID of the widget wrapper, which the response replaces.
   *
   * @return array<string, mixed>
   *   The button.
   */
  private function appendButton(FieldContext $context, string $wrapper_id): array {
    return [
      '#type' => 'submit',
      '#name' => ButtonNames::append($context->parents, $context->namespace),
      '#value' => new TranslatableMarkup('Append an item'),
      '#attributes' => ['class' => ['field-add-more-submit']],
      '#submit' => [[FormGeneratorDrupal::class, 'addMoreSubmit']],
      '#ajax' => [
        'callback' => [FormGeneratorDrupal::class, 'addMoreAjax'],
        'wrapper' => $wrapper_id,
        'effect' => 'fade',
      ],
    ];
  }

  /**
   * Gets the item limit from the array schema.
   *
   * @param \stdClass $schema
   *   The array schema.
   *
   * @return int
   *   The integer maxItems when it is at least 1, otherwise unlimited.
   */
  private static function cardinality(\stdClass $schema): int {
    $max_items = $schema->maxItems ?? NULL;
    return is_int($max_items) && $max_items >= 1
      ? $max_items
      : FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED;
  }

}
