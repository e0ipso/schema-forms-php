<?php

declare(strict_types=1);

namespace SchemaForms\Tests\Kernel\Form;

use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use SchemaForms\Drupal\FormGeneratorDrupal;
use Shaper\Util\Context;

/**
 * A form that embeds the output of FormGeneratorDrupal::transform().
 *
 * With no parent key the generated root container is the form itself.
 * Otherwise it sits at $form[$parent_key], and the form sets #tree only when
 * asked to.
 */
final class GeneratedSchemaForm implements FormInterface {

  /**
   * Creates the form.
   *
   * @param object $schema
   *   The decoded JSON Schema.
   * @param array $uiHints
   *   The UI schema, keyed by property name.
   * @param string|null $parentKey
   *   The form key that holds the generated container, or NULL to use the
   *   container as the form.
   * @param bool $tree
   *   Whether the form sets #tree. Only applies with a parent key.
   */
  public function __construct(
    private readonly object $schema,
    private readonly array $uiHints = [],
    private readonly ?string $parentKey = NULL,
    private readonly bool $tree = FALSE,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'schema_forms_generated_schema_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $context = new Context([
      'form_state' => $form_state,
      'ui_hints' => $this->uiHints,
    ]);
    $element = (new FormGeneratorDrupal())->transform($this->schema, $context);
    if ($this->parentKey === NULL) {
      return $element + $form;
    }
    $form['#tree'] = $this->tree;
    $form[$this->parentKey] = $element;
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {}

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {}

}
