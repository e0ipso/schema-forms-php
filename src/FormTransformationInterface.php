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
   *   NULL and an empty Context both mean that no option is set, and build
   *   the same form.
   *
   * @return mixed
   *   The form definition, never NULL. Each implementation documents its
   *   type; FormGeneratorDrupal returns a Drupal render array.
   *
   * @throws \TypeError
   *   When the schema is not a decoded draft-04 object schema, or the built
   *   form fails the output validator. The message lists the input errors.
   * @throws \SchemaForms\Exception\InvalidSchemaException
   *   When the implementation cannot represent the schema, such as a
   *   property name that FormGeneratorDrupal would read as a render property.
   *   It extends \InvalidArgumentException.
   * @throws \SchemaForms\Exception\InvalidOptionException
   *   When a context value has a value the implementation does not accept,
   *   such as an empty namespace. It extends \InvalidArgumentException.
   * @throws \SchemaForms\Exception\UnreadableMetaSchemaException
   *   When the bundled draft-04 meta-schema cannot be read.
   */
  #[\Override]
  public function transform($data, ?Context $context = NULL);

}
