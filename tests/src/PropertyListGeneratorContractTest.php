<?php

declare(strict_types=1);

namespace SchemaForms\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use SchemaForms\FormTransformationInterface;
use SchemaForms\Testing\FormTransformationContractTestCase;

/**
 * A generator built on the library's input validator keeps the contract.
 */
#[CoversClass(FormTransformationContractTestCase::class)]
final class PropertyListGeneratorContractTest extends FormTransformationContractTestCase {

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function createTransformation(): FormTransformationInterface {
    return new PropertyListGenerator();
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function assertForm(mixed $form, object $schema): void {
    $this->assertIsArray($form);
    $this->assertSame(array_keys((array) ($schema->properties ?? [])), $form['properties']);
  }

}
