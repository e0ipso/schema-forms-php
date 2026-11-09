<?php

declare(strict_types=1);

namespace SchemaForms\Tests\Kernel;

use Drupal\Component\Utility\Html;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use SchemaForms\Drupal\FormGeneratorDrupal;
use SchemaForms\Tests\Fixture\ConstantSchemas;

/**
 * Untrusted labels retain only the accepted inline HTML.
 */
#[RunTestsInSeparateProcesses]
final class SchemaLabelRenderingTest extends SchemaFormsKernelTestBase {

  use ConstantSchemas;

  /**
   * {@inheritdoc}
   */
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

  /**
   * Provides the sources an untrusted label can come from.
   *
   * @return array
   *   The test cases.
   */
  public static function labelProvider(): array {
    return [
      'schema title' => ['schema title'],
      'UI title' => ['ui:title'],
      'radio label' => ['enum'],
      'checkbox label' => ['checkbox enum'],
    ];
  }

  /**
   * Constant markup escapes text and JSON, labels it and renders no input.
   */
  #[DataProvider('renderedConstantProvider')]
  public function testRenderedConstants(mixed $value, string $text): void {
    $form = (new FormGeneratorDrupal())->transform(self::constantSchema($value));
    $html = (string) $this->container->get('renderer')->renderInIsolation($form['fixed']);
    $this->assertMatchesRegularExpression('@<label[^>]*>Fixed</label>\s*' . preg_quote(Html::escape($text), '@') . '\s*</div>@', $html);
    $this->assertStringNotContainsString('<input', $html);
  }

  /**
   * Provides constants with the text they render.
   *
   * @return array
   *   The test cases.
   */
  public static function renderedConstantProvider(): array {
    return self::constantProvider() + [
      'unsafe array' => [['<img src=x onerror=alert(1)>'], '["<img src=x onerror=alert(1)>"]'],
      'unsafe object' => [(object) ['<b>' => '<script>'], '{"<b>":"<script>"}'],
    ];
  }

  /**
   * A const-only schema renders exact escaped text.
   */
  public function testUntypedConstantEscapesExactText(): void {
    $text = '<script>alert("x")</script>&';
    $schema = (object) ['type' => 'object', 'properties' => (object) ['fixed' => (object) ['const' => $text]]];
    $result = $this->submitSchemaForm($schema, ['schema' => []], [], 'schema');
    $form = $result->form;
    $html = (string) $this->container->get('renderer')->renderInIsolation($form);
    $this->assertStringContainsString('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;&amp;', $html);
    $this->assertStringNotContainsString('<script>', $html);
    $this->assertSame($text, $result->values['schema']['fixed']);
    $this->assertSame([], $result->errors);
  }

}
