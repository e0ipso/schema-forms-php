<?php

declare(strict_types=1);

namespace SchemaForms\Tests;

use JsonSchema\Constraints\Constraint;
use SchemaForms\FormGeneratorInterface;
use SchemaForms\JsonSchemaFormValidator;
use Shaper\Transformation\TransformationBase;
use Shaper\Util\Context;
use Shaper\Validator\AcceptValidator;
use Shaper\Validator\ValidateableInterface;

/**
 * A generator without Drupal: it lists the schema's properties.
 *
 * It checks input with the validator FormGeneratorDrupal uses, so it accepts
 * and rejects the same schemas, and it refuses property names that start
 * with "#" the way FormGeneratorDrupal does.
 */
final class PropertyListGenerator extends TransformationBase implements FormGeneratorInterface {

  /**
   * The input validator, kept so a rejection message can read its errors.
   */
  private ?JsonSchemaFormValidator $inputValidator = NULL;

  /**
   * Lists the property names and the context the generator received.
   *
   * @param mixed $data
   *   The decoded schema.
   * @param \Shaper\Util\Context $context
   *   The context.
   *
   * @return array{properties: list<int|string>, context: array<mixed>}
   *   The property names and the context values.
   */
  #[\Override]
  protected function doTransform($data, Context $context): array {
    $names = array_keys((array) ($data->properties ?? []));
    foreach ($names as $name) {
      if (str_starts_with((string) $name, '#')) {
        throw new \InvalidArgumentException(sprintf('The property name "%s" starts with "#".', $name));
      }
    }
    return ['properties' => $names, 'context' => $context->getArrayCopy()];
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function getInputValidator(): ValidateableInterface {
    $this->inputValidator ??= new JsonSchemaFormValidator(Constraint::CHECK_MODE_TYPE_CAST);
    return $this->inputValidator;
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function getOutputValidator(): ValidateableInterface {
    return new AcceptValidator();
  }

}
