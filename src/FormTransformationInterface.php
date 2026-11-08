<?php

declare(strict_types=1);

namespace SchemaForms;

use Shaper\Transformation\TransformationInterface;
use Shaper\Util\Context;

/**
 * Turns a JSON Schema and a context into a form definition.
 *
 * Type-hint this interface in code that only builds forms. A replacement
 * generator then needs this one method; FormGeneratorInterface adds the
 * Shaper validation methods, which only Shaper pipelines call. Extend
 * FormTransformationContractTestCase to check an implementation.
 *
 * @see \SchemaForms\Testing\FormTransformationContractTestCase
 *
 * @api
 */
interface FormTransformationInterface extends TransformationInterface {

  /**
   * Builds the form for a schema.
   *
   * @param mixed $data
   *   A draft-04 JSON Schema decoded with json_decode() into objects, whose
   *   root type is "object".
   * @param \Shaper\Util\Context|null $context
   *   Options for the implementation, such as UI hints and saved values.
   *
   * @return mixed
   *   The form definition; FormGeneratorDrupal returns a Drupal render array.
   *
   * @throws \TypeError
   *   When the schema is not a decoded draft-04 object schema, or the built
   *   form fails the output validator. The message lists the input errors.
   * @throws \InvalidArgumentException
   *   When the implementation cannot represent the schema, such as a
   *   property name that FormGeneratorDrupal would read as a render property.
   */
  #[\Override]
  public function transform($data, ?Context $context = NULL);

}
