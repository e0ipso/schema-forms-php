<?php

declare(strict_types=1);

namespace SchemaForms\Drupal;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformStateInterface;

/**
 * Reads the value Form API holds for a generated element.
 *
 * @internal
 */
final class SubmittedValue {

  /**
   * Reads the stored value of an element.
   *
   * A plugin form embedded through a subform state resolves value paths
   * relative to the subform, while element #parents are absolute. The value
   * is read from the complete form state, where setValueForElement() and
   * setError() also resolve #parents, so validation sees what is stored.
   *
   * @param array<int|string, mixed> $element
   *   The element, with its #parents.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state, or a subform state.
   *
   * @return mixed
   *   The stored value, or NULL when there is none.
   */
  public static function read(array $element, FormStateInterface $form_state): mixed {
    $complete_state = $form_state instanceof SubformStateInterface
      ? $form_state->getCompleteFormState()
      : $form_state;
    $parents = $element['#parents'] ?? [];
    return $complete_state->getValue(is_array($parents) || is_string($parents) ? $parents : []);
  }

}
