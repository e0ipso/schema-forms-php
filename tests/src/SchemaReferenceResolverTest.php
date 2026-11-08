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
use SchemaForms\Exception\ExceptionInterface;
use SchemaForms\Exception\UnsupportedReferenceException;
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
            $this->assertInstanceOf(UnsupportedReferenceException::class, $e);
            $this->assertInstanceOf(ExceptionInterface::class, $e);
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

  /**
   * Registration and refinement leave the caller's schema unchanged.
   */
  public function testCallerSchemaIsNotRewritten(): void {
    $json = '{"type":"object","definitions":{"label":{"type":"string","minLength":2}},"properties":{"field":{"type":"string","allOf":[{"$ref":"#/definitions/label"}]},"list":{"type":"array","items":{"$ref":"#/definitions/label"}}}}';
    $schema = json_decode($json);
    $factory = SchemaReferenceResolver::createFactory($schema);
    $this->assertEquals(json_decode($json), $schema);
    $registered = SchemaReferenceResolver::registeredSchema($factory);
    $this->assertNotSame($schema, $registered);
    $this->assertSame('internal://schema-with-refs/#/definitions/label', $registered->properties->field->allOf[0]->{'$ref'});
    $this->assertSame([], RecursiveTypeCaster::recursiveTypeRefinements(['field' => ''], $schema));
    $this->assertEquals(json_decode($json), $schema);
  }

  /**
   * A local path as a base URI is refused before any filesystem check.
   *
   * UriResolver runs is_file() and is_dir() on such a base, which a stat
   * recorder on the file wrapper observes.
   */
  #[DataProvider('localBaseProvider')]
  public function testLocalBaseIsNotProbed(string $entry): void {
    $sentinel = sys_get_temp_dir() . '/schema-forms-sentinel-' . bin2hex(random_bytes(8));
    $operation = match ($entry) {
      'extends' => static fn () => SchemaReferenceResolver::createFactory((object) ['type' => 'object', 'extends' => $sentinel]),
      'retrieve' => static fn () => (new SchemaReferenceResolver())->retrieve('schema.json', $sentinel),
    };
    // Load every class the operation needs before observing the wrapper.
    try {
      $operation();
    }
    catch (UnsupportedReferenceException) {
    }
    try {
      StatRecorder::observe($operation);
      $this->fail('A local base URI must be refused.');
    }
    catch (UnsupportedReferenceException $e) {
      $this->assertStringContainsString('retrieval is disabled', $e->getMessage());
    }
    $this->assertSame([], array_values(array_filter(StatRecorder::$paths, static fn (string $path) => str_contains($path, $sentinel))));
  }

  /**
   * An absolute "extends" URI still reaches the bundled metadata.
   */
  public function testAbsoluteExtendsIsResolved(): void {
    $factory = SchemaReferenceResolver::createFactory((object) ['extends' => 'http://json-schema.org/draft-04/schema#']);
    $metadata = $factory->getSchemaStorage()->getSchema('http://json-schema.org/draft-04/schema#');
    $this->assertSame('http://json-schema.org/draft-04/schema#', $metadata->id);
  }

  public static function localBaseProvider(): array {
    return ['extends' => ['extends'], 'retrieve' => ['retrieve']];
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

/**
 * Records the paths the file wrapper is asked to stat.
 */
final class StatRecorder {

  public $context;
  public static array $paths = [];

  public static function observe(callable $callback): mixed {
    self::$paths = [];
    if (!stream_wrapper_unregister('file') || !stream_wrapper_register('file', self::class)) {
      throw new \RuntimeException('Cannot replace the file wrapper.');
    }
    try {
      return $callback();
    }
    finally {
      stream_wrapper_unregister('file');
      stream_wrapper_restore('file');
    }
  }

  public function url_stat(string $path, int $flags): array|false {
    self::$paths[] = $path;
    return FALSE;
  }

  public function stream_open(string $path, string $mode, int $options, ?string &$opened_path): bool {
    self::$paths[] = $path;
    return FALSE;
  }

}
