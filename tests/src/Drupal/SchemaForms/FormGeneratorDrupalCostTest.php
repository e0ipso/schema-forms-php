<?php

declare(strict_types=1);

namespace SchemaForms\Tests\Drupal\SchemaForms;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SchemaForms\Drupal\FormGeneratorDrupal;
use SchemaForms\Tests\Fixture\TranslationContainer;

/**
 * The generator's work stays bounded on repeated schemas.
 */
#[CoversClass(FormGeneratorDrupal::class)]
final class FormGeneratorDrupalCostTest extends TestCase {

  use TranslationContainer;

  /**
   * A schema the generator accepted is not validated again.
   */
  public function testRepeatedSchemaIsValidatedOnce(): void {
    $generator = new FormGeneratorDrupal();
    $json = '{"type":"object","properties":{"a":{"type":"string"}}}';
    $first = $generator->transform(json_decode($json));
    $validator = $generator->getInputValidator();
    $this->assertFalse($validator->isValid('not a schema'));
    $errors = $validator->getErrors();
    $this->assertNotSame([], $errors);

    // Validation starts by clearing the errors, so they survive only when
    // the second transform skips it.
    $second = $generator->transform(json_decode($json));

    $this->assertSame($errors, $validator->getErrors());
    $this->assertSame($first['#json_schema'], $second['#json_schema']);

    $generator->transform(json_decode('{"type":"object","properties":{"b":{"type":"string"}}}'));

    $this->assertSame([], $validator->getErrors());
  }

  /**
   * Only transform() marks a schema as accepted.
   */
  public function testDirectBuildDoesNotSkipValidation(): void {
    $generator = new FormGeneratorDrupal();
    $schema = json_decode('{"type":"object","properties":{"a":{"type":"string"}}}');
    $generator->doTransform($schema);
    $validator = $generator->getInputValidator();
    $validator->isValid('not a schema');

    $generator->transform($schema);

    $this->assertSame([], $validator->getErrors());
  }

  /**
   * A rejected schema is rejected every time.
   */
  public function testRejectedSchemaIsNotRemembered(): void {
    $generator = new FormGeneratorDrupal();
    $schema = json_decode('{"type":"array"}');
    foreach ([1, 2] as $attempt) {
      try {
        $generator->transform($schema);
        $this->fail('Attempt ' . $attempt . ' accepted an array root.');
      }
      catch (\TypeError $e) {
        $this->assertStringContainsString('received invalid input data', $e->getMessage());
      }
    }
  }

  /**
   * The remembered schemas stay out of the serialized generator.
   */
  public function testMemoryIsNotSerialized(): void {
    $generator = new FormGeneratorDrupal();
    $generator->transform(json_decode('{"type":"object","properties":{"a":{"type":"string"}}}'));

    $this->assertStringNotContainsString('checkedSchemas', serialize($generator));
  }

}
