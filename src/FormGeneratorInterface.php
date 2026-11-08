<?php

declare(strict_types=1);

namespace SchemaForms;

use Shaper\Transformation\TransformationValidationInterface;

/**
 * Builds a form definition from a JSON Schema document.
 *
 * Implementations validate the schema before building and the form after it,
 * through the validators of TransformationValidationInterface. Code that only
 * calls transform() should type-hint FormTransformationInterface instead.
 *
 * @api
 */
interface FormGeneratorInterface extends FormTransformationInterface, TransformationValidationInterface {}
