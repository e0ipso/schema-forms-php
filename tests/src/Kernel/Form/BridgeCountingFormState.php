<?php

declare(strict_types=1);

namespace SchemaForms\Tests\Kernel\Form;

use Drupal\Core\Form\FormState;

/**
 * A form state that counts how often the schema bridge runs.
 *
 * Every run of the bridge type-casts the values and writes them back with
 * setValueForElement() on the generated root container, the only element
 * that carries #json_schema.
 */
final class BridgeCountingFormState extends FormState {

  /**
   * The number of bridge runs seen so far.
   */
  public int $bridgeRuns = 0;

  /**
   * {@inheritdoc}
   */
  public function setValueForElement(array $element, $value) {
    if (isset($element['#json_schema'])) {
      $this->bridgeRuns++;
    }
    return parent::setValueForElement($element, $value);
  }

}
