<?php

declare(strict_types=1);

namespace SchemaForms;

use Shaper\Transformation\TransformationInterface;
use Shaper\Transformation\TransformationValidationInterface;

/**
 * Interface for a form generator.
 */
interface FormGeneratorInterface extends TransformationInterface, TransformationValidationInterface {}
