<?php

declare(strict_types=1);

namespace SchemaForms\Tests\Kernel\Form;

use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use SchemaForms\Drupal\EnumElements;
use SchemaForms\Drupal\FormGeneratorDrupal;
use SchemaForms\Drupal\MultivalueCallbacks;
use SchemaForms\Drupal\SchemaValidationBridge;

/**
 * A generated form whose callbacks are named the way 3.1.1 named them.
 *
 * Forms cached by release 3.1.1 name the Form API callbacks of
 * FormGeneratorDrupal, and the validation callbacks of a generator
 * serialized with no properties. This form builds the current render array,
 * then renames every callback back, so a submission runs the deprecated
 * forwarders.
 */
final class LegacyCallbackSchemaForm implements FormInterface {

  /**
   * The static callbacks generated forms name now, and their 3.1.1 names.
   */
  private const STATIC_CALLBACKS = [
    MultivalueCallbacks::class . '::process' => 'multiValueProcess',
    MultivalueCallbacks::class . '::afterBuild' => 'multiValueAfterBuild',
    MultivalueCallbacks::class . '::removeSubmit' => 'removeOneSubmit',
    MultivalueCallbacks::class . '::removeAjax' => 'removeOneAjax',
    MultivalueCallbacks::class . '::appendSubmit' => 'addMoreSubmit',
    MultivalueCallbacks::class . '::appendAjax' => 'addMoreAjax',
    EnumElements::class . '::validateCheckboxes' => 'validateCheckboxes',
  ];

  /**
   * The validation callbacks generated forms name now, and their 3.1.1 names.
   */
  private const VALIDATION_CALLBACKS = [
    SchemaValidationBridge::class . '::elementValidate' => 'validateWithSchema',
    SchemaValidationBridge::class . '::formValidate' => 'validateFormWithSchema',
  ];

  /**
   * The keys that hold lists of callbacks.
   */
  private const CALLBACK_LISTS = ['#process', '#after_build', '#submit', '#element_validate', '#validate'];

  /**
   * Creates the form.
   *
   * @param \SchemaForms\Tests\Kernel\Form\GeneratedSchemaForm $inner
   *   The form that builds the current render array.
   */
  public function __construct(
    private readonly GeneratedSchemaForm $inner,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return $this->inner->getFormId();
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    // The generator as release 3.1.1 serialized it into the form cache.
    $class = FormGeneratorDrupal::class;
    // phpcs:ignore DrupalPractice.FunctionCalls.InsecureUnserialize -- Drupal's form cache restores every class, as this does.
    $generator = unserialize('O:' . strlen($class) . ':"' . $class . '":0:{}');
    return self::rename($this->inner->buildForm($form, $form_state), $generator);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $this->inner->validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {}

  /**
   * Renames the callbacks of an element and its children.
   *
   * @param array<int|string, mixed> $element
   *   The element.
   * @param \SchemaForms\Drupal\FormGeneratorDrupal $generator
   *   The generator the validation callbacks name.
   *
   * @return array<int|string, mixed>
   *   The element.
   */
  private static function rename(array $element, FormGeneratorDrupal $generator): array {
    foreach ($element as $key => $value) {
      if (in_array($key, self::CALLBACK_LISTS, TRUE) && is_array($value)) {
        $element[$key] = array_map(static fn (mixed $callback): mixed => self::legacy($callback, $generator), $value);
        continue;
      }
      if ($key === '#ajax' && is_array($value) && isset($value['callback'])) {
        $element[$key]['callback'] = self::legacy($value['callback'], $generator);
        continue;
      }
      if (is_array($value) && !str_starts_with((string) $key, '#')) {
        $element[$key] = self::rename($value, $generator);
      }
    }
    return $element;
  }

  /**
   * Gives the 3.1.1 name of a callback.
   *
   * @param mixed $callback
   *   The callback.
   * @param \SchemaForms\Drupal\FormGeneratorDrupal $generator
   *   The generator the validation callbacks name.
   *
   * @return mixed
   *   The callback 3.1.1 named, or the callback when it did not change.
   */
  private static function legacy(mixed $callback, FormGeneratorDrupal $generator): mixed {
    $name = is_array($callback) && is_string($callback[0] ?? NULL) ? $callback[0] . '::' . ($callback[1] ?? '') : '';
    return match (TRUE) {
      isset(self::STATIC_CALLBACKS[$name]) => [FormGeneratorDrupal::class, self::STATIC_CALLBACKS[$name]],
      isset(self::VALIDATION_CALLBACKS[$name]) => [$generator, self::VALIDATION_CALLBACKS[$name]],
      default => $callback,
    };
  }

}
