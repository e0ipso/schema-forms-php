<?php

namespace SchemaForms\Tests;

use Drupal\Core\Form\FormState;
use JsonSchema\Exception\UnresolvableJsonPointerException;
use JsonSchema\Validator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SchemaForms\Drupal\FormGeneratorDrupal;
use SchemaForms\Drupal\FormValidatorDrupal;
use SchemaForms\RecursiveTypeCaster;
use SchemaForms\SchemaReferenceResolver;
use SchemaForms\Tests\Fixture\ReferenceSentinel;

/**
 * Retrieval stays inside registered schemas and bundled metadata.
 */
#[CoversClass(SchemaReferenceResolver::class)]
#[CoversClass(FormGeneratorDrupal::class)]
#[CoversClass(FormValidatorDrupal::class)]
#[CoversClass(RecursiveTypeCaster::class)]
final class SchemaReferenceResolverTest extends TestCase {

  /**
   * The sentinel also detects the pre-fix retrieval without external hosts.
   */
  #[DataProvider('externalReferenceProvider')]
  public function testExternalRetrievalIsBlocked(string $scheme, string $entry): void {
    $file = tempnam(sys_get_temp_dir(), 'schema-ref-');
    file_put_contents($file, '{"type":"string","minLength":2}');
    $uri = $scheme === 'file' ? 'file://' . $file : $scheme . '://127.0.0.1:9/sentinel.json';
    $schema = (object) ['type' => 'object', 'properties' => (object) [
      'field' => (object) ['type' => 'string', 'allOf' => [(object) ['$ref' => $uri]]],
    ]];
    try {
      // Prove the observer is armed for this exact URI before the operation.
      ReferenceSentinel::observe($file, static fn () => file_get_contents($uri));
      $this->assertCount(1, ReferenceSentinel::$reads);
      ReferenceSentinel::observe($file, function () use ($schema, $entry): void {
        if ($entry === 'generation') {
          $form = (new FormGeneratorDrupal())->transform($schema);
          $this->assertArrayNotHasKey('field', $form);
          $this->assertStringContainsString('unsupported reference', $form['#markup']->getUntranslatedString());
          return;
        }
        if ($entry === 'optional blank') {
          try {
            RecursiveTypeCaster::recursiveTypeRefinements(['field' => ''], $schema);
            $this->fail('Optional blank refinement must reject the reference.');
          }
          catch (UnresolvableJsonPointerException $e) {
            $this->assertStringContainsString('retriev', $e->getMessage());
          }
          return;
        }
        $form = ['#parents' => ['schema'], '#array_parents' => ['schema']];
        $state = (new FormState())->setUserInput([])->setValues(['schema' => ['field' => 'valid']]);
        FormValidatorDrupal::validateWithSchema($form, $state, $schema);
        $this->assertSame(['schema'], array_keys($state->getErrors()));
        $error = $state->getErrors()['schema'];
        $this->assertStringContainsString('unsupported reference', $error->getUntranslatedString());
      });
      $this->assertSame([], ReferenceSentinel::$reads, 'No file read or HTTP(S) retrieval was attempted.');
    }
    finally {
      unlink($file);
      (new FormState())->clearErrors();
    }
  }

  public static function externalReferenceProvider(): array {
    $cases = [];
    foreach (['file', 'http', 'https'] as $scheme) {
      foreach (['generation', 'submission', 'optional blank'] as $entry) {
        $cases[$scheme . ' ' . $entry] = [$scheme, $entry];
      }
    }
    return $cases;
  }

  #[DataProvider('internalReferenceProvider')]
  public function testInternalReferences(string $reference): void {
    $schema = (object) ['type' => 'object', 'definitions' => (object) [
      'label' => (object) ['type' => 'string', 'minLength' => 2],
    ], 'properties' => (object) ['field' => (object) ['type' => 'string', 'allOf' => [(object) ['$ref' => $reference]]]]];
    $form = (new FormGeneratorDrupal())->transform($schema);
    $this->assertSame('textfield', $form['field']['#type']);
    $this->assertSame([], RecursiveTypeCaster::recursiveTypeRefinements(['field' => ''], $schema));
    $validator = new Validator(SchemaReferenceResolver::createFactory($schema));
    $data = (object) ['field' => 'valid'];
    $validator->validate($data, $schema);
    $this->assertTrue($validator->isValid());
    $validator->reset();
    $data->field = 'x';
    $validator->validate($data, $schema);
    $this->assertFalse($validator->isValid());
  }

  public static function internalReferenceProvider(): array {
    return [
      'fragment' => ['#/definitions/label'],
      'legacy registered URI' => [SchemaReferenceResolver::SCHEMA_URI . '#/definitions/label'],
      'normalized URI' => ['internal://schema-with-refs/#/definitions/label'],
    ];
  }

  #[DataProvider('metadataProvider')]
  public function testBundledMetadata(string $uri): void {
    $schema = (object) ['$ref' => $uri];
    $validator = new Validator(SchemaReferenceResolver::createFactory($schema));
    $data = (object) ['type' => 'string'];
    $validator->validate($data, $schema);
    $this->assertTrue($validator->isValid());
    $validator->reset();
    $data->type = 'not-a-type';
    $validator->validate($data, $schema);
    $this->assertFalse($validator->isValid());
  }

  public static function metadataProvider(): array {
    return [
      'bundled file' => ['file://' . dirname(__DIR__, 2) . '/meta-schema-draft4.json'],
      'HTTP alias' => ['http://json-schema.org/draft-04/schema'],
      'HTTPS alias' => ['https://json-schema.org/draft-04/schema#'],
    ];
  }

}
