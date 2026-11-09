<?php

declare(strict_types=1);

namespace SchemaForms\Tests;

use JsonSchema\Validator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SchemaForms\OptionalBlankRule;
use SchemaForms\RestrictedSchemaRegistry;

/**
 * Only an optional blank that its schema rejects is omitted.
 */
#[CoversClass(OptionalBlankRule::class)]
final class OptionalBlankRuleTest extends TestCase {

  /**
   * Decides each case with the restricted validator.
   */
  #[DataProvider('omissionProvider')]
  public function testOmission(int|string $key, mixed $submitted, mixed $refined, string $property, mixed $required, bool $expected): void {
    $rule = new OptionalBlankRule(new Validator((new RestrictedSchemaRegistry())->factory()));
    $property_schema = json_decode($property);
    $schema = (object) [
      'type' => 'object',
      'required' => $required,
      'properties' => (object) [$key => $property_schema],
    ];
    $this->assertSame($expected, $rule->omits($key, $submitted, $refined, $schema, $property_schema));
  }

  /**
   * Data provider for testOmission().
   */
  public static function omissionProvider(): array {
    $rejects = '{"type":"string","minLength":1}';
    return [
      'optional blank the schema rejects' => ['name', '', '', $rejects, [], TRUE],
      'optional blank the schema accepts' => ['name', '', '', '{"type":"string"}', [], FALSE],
      'required blank' => ['name', '', '', $rejects, ['name'], FALSE],
      'required decimal name' => [123, '', '', $rejects, ['123'], FALSE],
      'required keyword that is not a list' => ['name', '', '', $rejects, 'name', TRUE],
      'blank refined to NULL' => ['name', '', NULL, $rejects, [], FALSE],
      'value the schema rejects' => ['name', 'x', 'x', '{"type":"string","minLength":2}', [], FALSE],
    ];
  }

  /**
   * The validator is asked about optional blanks and nothing else.
   *
   * Every submitted property passes through the rule, so validating each one
   * would run the validator once per property of every submission.
   */
  public function testValidatesOnlyOptionalBlanks(): void {
    $validator = new class((new RestrictedSchemaRegistry())->factory()) extends Validator {

      /**
       * The values validated, in order.
       *
       * @var list<mixed>
       */
      public array $validated = [];

      /**
       * Records the value, then validates it.
       *
       * The signature fits both supported json-schema majors.
       */
      public function validate(&$value, $schema = NULL, $checkMode = NULL): int {
        $this->validated[] = $value;
        return parent::validate($value, $schema, $checkMode);
      }

    };
    $rule = new OptionalBlankRule($validator);
    $schema = json_decode('{"type":"object","required":["kept"],"properties":{"kept":{"type":"string","minLength":1},"optional":{"type":"string","minLength":1}}}');
    $property = $schema->properties->optional;
    $this->assertFalse($rule->omits('kept', '', '', $schema, $schema->properties->kept));
    $this->assertFalse($rule->omits('optional', 'x', 'x', $schema, $property));
    $this->assertFalse($rule->omits('optional', '', NULL, $schema, $property));
    $this->assertSame([], $validator->validated);
    $this->assertTrue($rule->omits('optional', '', '', $schema, $property));
    $this->assertSame([''], $validator->validated);
  }

  /**
   * Blanks are checked with the references the injected factory resolves.
   */
  public function testUsesTheInjectedFactory(): void {
    $factory = (new RestrictedSchemaRegistry())->factory();
    $factory->getSchemaStorage()->addSchema('internal://labels/', json_decode('{"definitions":{"label":{"type":"string","minLength":1}}}'));
    $rule = new OptionalBlankRule(new Validator($factory));
    $schema = json_decode('{"type":"object","properties":{"label":{"$ref":"internal://labels/#/definitions/label"}}}');
    $this->assertTrue($rule->omits('label', '', '', $schema, $schema->properties->label));
  }

}
