<?php

declare(strict_types=1);

namespace SchemaForms\Tests\Drupal;

use PHPUnit\Framework\Attributes\CoversClass;
use SchemaForms\Drupal\RenderArrayValidator;
use SchemaForms\Tests\ValidatorContractTestCase;
use Shaper\Validator\ValidateableInterface;

/**
 * RenderArrayValidator keeps the Shaper validator contract.
 */
#[CoversClass(RenderArrayValidator::class)]
final class RenderArrayValidatorContractTest extends ValidatorContractTestCase {

  /**
   * {@inheritdoc}
   */
  protected function createValidator(): ValidateableInterface {
    return new RenderArrayValidator();
  }

  /**
   * {@inheritdoc}
   */
  public static function acceptedInputProvider(): array {
    return [
      'render property' => [['#type' => 'item']],
      'nested render array' => [['child' => ['#markup' => 'x']]],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function rejectedInputProvider(): array {
    return [
      'no render property' => [['child' => 'x']],
      'empty array' => [[]],
      'string' => ['#type'],
    ];
  }

}
