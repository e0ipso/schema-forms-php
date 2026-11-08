<?php

namespace SchemaForms\Tests\Drupal;

use Drupal\Core\Form\FormState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SchemaForms\Drupal\FormGeneratorDrupal;
use SchemaForms\Drupal\FormValidatorDrupal;
use SchemaForms\Drupal\SchemaPreparer;
use SchemaForms\Drupal\SubmissionPreparer;
use SchemaForms\Drupal\SubmissionValidator;
use SchemaForms\SchemaReferenceResolver;
use SchemaForms\Tests\Fixture\ReferenceSentinel;

/**
 * Generation and submission keep retrieval inside registered schemas.
 */
#[CoversClass(FormGeneratorDrupal::class)]
#[CoversClass(FormValidatorDrupal::class)]
#[CoversClass(SchemaReferenceResolver::class)]
#[CoversClass(SchemaPreparer::class)]
#[CoversClass(SubmissionPreparer::class)]
#[CoversClass(SubmissionValidator::class)]
final class SchemaReferenceRetrievalTest extends TestCase {

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
        $form = ['#parents' => ['schema'], '#array_parents' => ['schema']];
        $state = (new FormState())->setUserInput([])->setValues(['schema' => ['field' => 'valid']]);
        if ($entry === 'casting') {
          FormValidatorDrupal::typeCastRecursive($form, $state, $schema);
        }
        if ($entry === 'submission') {
          FormValidatorDrupal::validateWithSchema($form, $state, $schema);
        }
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
      foreach (['generation', 'casting', 'submission'] as $entry) {
        $cases[$scheme . ' ' . $entry] = [$scheme, $entry];
      }
    }
    return $cases;
  }

  /**
   * A field whose schema references a definition still renders.
   */
  #[DataProvider('internalReferenceProvider')]
  public function testInternalReferences(string $reference): void {
    $schema = (object) ['type' => 'object', 'definitions' => (object) [
      'label' => (object) ['type' => 'string', 'minLength' => 2],
    ], 'properties' => (object) ['field' => (object) ['type' => 'string', 'allOf' => [(object) ['$ref' => $reference]]]]];
    $form = (new FormGeneratorDrupal())->transform($schema);
    $this->assertSame('textfield', $form['field']['#type']);
  }

  public static function internalReferenceProvider(): array {
    return [
      'fragment' => ['#/definitions/label'],
      'legacy registered URI' => [SchemaReferenceResolver::SCHEMA_URI . '#/definitions/label'],
      'normalized URI' => ['internal://schema-with-refs/#/definitions/label'],
    ];
  }

  /**
   * Generation leaves the caller's schema as it was.
   *
   * The render array keeps the registered copy, whose references name the
   * internal document, as it did when the caller's schema was rewritten.
   */
  public function testCallerSchemaIsNotRewritten(): void {
    $json = '{"type":"object","definitions":{"label":{"type":"string","minLength":2}},"properties":{"field":{"type":"string","allOf":[{"$ref":"#/definitions/label"}]}}}';
    $schema = json_decode($json);
    $form = (new FormGeneratorDrupal())->transform($schema);
    $this->assertEquals(json_decode($json), $schema);
    $this->assertSame('internal://schema-with-refs/#/definitions/label', $form['#json_schema']->properties->field->allOf[0]->{'$ref'});
  }

}
