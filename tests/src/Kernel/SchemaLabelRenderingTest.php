<?php

declare(strict_types=1);

namespace SchemaForms\Tests\Kernel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\Component\Utility\Html;
use SchemaForms\Drupal\FormGeneratorDrupal;
use SchemaForms\Tests\Drupal\SchemaForms\FormGeneratorDrupalTest;
use Shaper\Util\Context;

/**
 * SCHE-0049: untrusted labels retain only the accepted inline HTML.
 */
#[CoversClass(FormGeneratorDrupal::class)]
#[RunTestsInSeparateProcesses]
final class SchemaLabelRenderingTest extends SchemaFormsKernelTestBase {

  protected static $modules = ['system'];

  private const LABEL = '<img src="x" onerror="alert(1)"><em>emphasis</em><strong>strong</strong><a href="https://example.com">link</a>';

  /**
   * Tests actual form rendering, including the labels on checkbox children.
   */
  #[DataProvider('labelProvider')]
  public function testRenderedLabels(string $source): void {
    $property = (object) ['type' => 'string', 'title' => self::LABEL];
    $hints = [];
    if ($source === 'ui:title') {
      $property->title = 'Overridden';
      $hints = ['field' => ['ui:title' => self::LABEL]];
    }
    if ($source === 'enum') {
      $property = (object) ['type' => 'string', 'enum' => ['option']];
      $hints = ['field' => ['ui:enum' => ['labels' => ['mappings' => ['option' => self::LABEL]]]]];
    }
    if ($source === 'checkbox enum') {
      $property = (object) ['type' => 'array', 'items' => (object) ['type' => 'string', 'enum' => ['option']]];
      $hints = ['field' => ['ui:enum' => ['labels' => ['mappings' => ['option' => self::LABEL]]]]];
    }
    $schema = (object) ['type' => 'object', 'properties' => (object) ['field' => $property]];
    $result = $this->submitSchemaForm($schema, ['schema' => []], $hints, 'schema');
    $form = $result->form;
    $html = (string) $this->container->get('renderer')->renderInIsolation($form);
    $this->assertStringNotContainsString('<img', $html);
    $this->assertStringNotContainsString('onerror', $html);
    $this->assertStringContainsString('<em>emphasis</em>', $html);
    $this->assertStringContainsString('<strong>strong</strong>', $html);
    $this->assertStringContainsString('<a href="https://example.com">link</a>', $html);
  }

  public static function labelProvider(): array {
    return [
      'schema title' => ['schema title'],
      'UI title' => ['ui:title'],
      'radio label' => ['enum'],
      'checkbox label' => ['checkbox enum'],
    ];
  }

  /**
   * Title filtering preserves other metadata and fixed text.
   */
  public function testOtherMetadataIsUnchanged(): void {
    $schema = (object) ['type' => 'object', 'properties' => (object) [
      'fixed' => (object) ['type' => 'string', 'const' => self::LABEL],
      'field' => (object) ['type' => 'string', 'description' => self::LABEL],
    ]];
    $form = (new FormGeneratorDrupal())->transform($schema, new Context([
      'ui_hints' => ['field' => ['ui:placeholder' => self::LABEL]],
    ]));
    $this->assertSame(self::LABEL, $form['fixed']['#plain_text']);
    $this->assertSame(self::LABEL, (string) $form['field']['#description']);
    $this->assertSame(self::LABEL, $form['field']['#placeholder']);
  }


  /**
   * SCHE-0029: constant markup escapes text and JSON and renders no input.
   */
  #[DataProvider('renderedConstantProvider')]
  public function testRenderedConstants(mixed $value, string $text): void {
    $schema = (object) ['type' => 'object', 'properties' => (object) ['fixed' => (object) ['type' => match (gettype($value)) {
      'integer' => 'integer', 'double' => 'number', 'boolean' => 'boolean',
      'array' => 'array', 'object' => 'object', 'NULL' => 'null', default => 'string',
    }, 'const' => $value]]];
    $form = (new FormGeneratorDrupal())->transform($schema);
    $html = (string) $this->container->get('renderer')->renderInIsolation($form['fixed']);
    $this->assertSame(Html::escape($text), $html);
    $this->assertStringNotContainsString('<input', $html);
  }

  public static function renderedConstantProvider(): array {
    return FormGeneratorDrupalTest::constantProvider() + [
      'unsafe array' => [['<img src=x onerror=alert(1)>'], '["<img src=x onerror=alert(1)>"]'],
      'unsafe object' => [(object) ['<b>' => '<script>'], '{"<b>":"<script>"}'],
    ];
  }

}
