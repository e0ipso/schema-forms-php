<?php

declare(strict_types=1);

namespace SchemaForms\Tests;

use JsonSchema\Constraints\Constraint;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SchemaForms\JsonSchemaFormValidator;
use SchemaForms\RecursiveTypeCaster;
use SchemaForms\RestrictedSchemaRegistry;
use SchemaForms\SchemaReferenceResolver;
use SchemaForms\SubmissionRefiner;

/**
 * One process can serve many schemas without one call leaking into the next.
 *
 * Workers such as RoadRunner or Swoole keep PHP objects and class state
 * between requests, so a static cache keyed on the shared internal schema URI
 * would hand one request's schema to the next.
 */
#[CoversClass(JsonSchemaFormValidator::class)]
#[CoversClass(RecursiveTypeCaster::class)]
#[CoversClass(SchemaReferenceResolver::class)]
#[CoversClass(SubmissionRefiner::class)]
#[CoversClass(RestrictedSchemaRegistry::class)]
final class LongRunningProcessTest extends TestCase {

  /**
   * The core declares no static properties and no static variables.
   */
  public function testCoreKeepsNoStaticState(): void {
    $src = dirname(__DIR__, 2) . '/src/';
    $files = [...glob($src . '*.php'), ...glob($src . 'Exception/*.php')];
    $this->assertNotEmpty($files);
    $state = [];
    foreach ($files as $file) {
      $class = new \ReflectionClass('SchemaForms\\' . str_replace([$src, '/', '.php'], ['', '\\', ''], $file));
      foreach ($class->getProperties(\ReflectionProperty::IS_STATIC) as $property) {
        $state[] = $class->getName() . '::$' . $property->getName();
      }
      $tokens = array_values(array_filter(
        \PhpToken::tokenize((string) file_get_contents($file)),
        static fn (\PhpToken $token): bool => !$token->isIgnorable(),
      ));
      foreach ($tokens as $index => $token) {
        if ($token->is(T_STATIC) && ($tokens[$index + 1] ?? NULL)?->is(T_VARIABLE)) {
          $state[] = $file . ':' . $token->line;
        }
      }
    }
    $this->assertSame([], $state);
  }

  /**
   * Refining one schema does not change how the next one is refined.
   *
   * Both schemas register under the same internal URI and reference the same
   * pointer, so a factory or storage cached on that URI would resolve the
   * second call's reference to the first call's definition.
   */
  public function testTypeCastingIsolatesSchemas(): void {
    $rejects_blank = '{"type":"object","definitions":{"name":{"type":"string","minLength":1}},"properties":{"name":{"$ref":"#/definitions/name"},"count":{"type":"integer"}}}';
    $accepts_blank = '{"type":"object","definitions":{"name":{"type":"string"}},"properties":{"name":{"$ref":"#/definitions/name"},"count":{"type":"string"}}}';
    $refine = static fn (string $schema): mixed => (new SubmissionRefiner())->refine(
      json_decode('{"name":"","count":"1"}'),
      json_decode($schema),
    );
    $first = $refine($rejects_blank);
    $this->assertEquals((object) ['count' => 1], $first);
    $this->assertSame(1, $first->count);
    $second = $refine($accepts_blank);
    $this->assertEquals((object) ['name' => '', 'count' => '1'], $second);
    $this->assertSame('1', $second->count);
    $this->assertSame(1, $refine($rejects_blank)->count);
  }

  /**
   * One validator checks a sequence of schemas independently.
   */
  public function testValidatorIsolatesSchemas(): void {
    $validator = new JsonSchemaFormValidator(Constraint::CHECK_MODE_TYPE_CAST);
    $invalid = json_decode('{"type":"object","required":"name"}');
    $valid = json_decode('{"type":"object","properties":{"name":{"type":"string"}}}');
    $this->assertFalse($validator->isValid($invalid));
    $errors = $validator->getErrors();
    $this->assertNotEmpty($errors);
    $this->assertTrue($validator->isValid($valid));
    $this->assertSame([], $validator->getErrors());
    $this->assertFalse($validator->isValid($invalid));
    $this->assertSame($errors, $validator->getErrors());
  }

}
