<?php

declare(strict_types=1);

namespace SchemaForms;

use Shaper\Transformation\TransformationInterface;
use Shaper\Transformation\TransformationValidationInterface;

/**
 * Builds a form definition from a JSON Schema document.
 *
 * @api
 */
interface FormGeneratorInterface extends TransformationInterface, TransformationValidationInterface {}
