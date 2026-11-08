<?php

declare(strict_types=1);

namespace SchemaForms\Tests\Kernel;

use Drupal\Component\Utility\Html;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use SchemaForms\Drupal\ButtonNames;
use SchemaForms\Drupal\ElementScaffold;
use SchemaForms\Drupal\EnumElements;
use SchemaForms\Drupal\FieldBuilder;
use SchemaForms\Drupal\FieldContext;
use SchemaForms\Drupal\FormGeneratorDrupal;
use SchemaForms\Drupal\FormTreeBuilder;
use SchemaForms\Drupal\Labels;
use SchemaForms\Drupal\MultivalueCallbacks;
use SchemaForms\Drupal\MultivalueWidgetBuilder;
use SchemaForms\Drupal\RenderChildren;
use SchemaForms\Drupal\RequiredMarker;
use SchemaForms\Drupal\SavedInput;
use SchemaForms\Drupal\SchemaPreparer;
use SchemaForms\Drupal\SchemaTypes;
use SchemaForms\Drupal\UnsupportedElement;
use SchemaForms\Drupal\WidgetButton;
use SchemaForms\Drupal\WidgetState;
use SchemaForms\Drupal\WidgetStateStorage;
use SchemaForms\Tests\Fixture\ConstantSchemas;

/**
 * Untrusted labels retain only the accepted inline HTML.
 */
#[CoversClass(FormGeneratorDrupal::class)]
#[CoversClass(ButtonNames::class)]
#[CoversClass(ElementScaffold::class)]
#[CoversClass(EnumElements::class)]
#[CoversClass(FieldBuilder::class)]
#[CoversClass(FieldContext::class)]
#[CoversClass(FormTreeBuilder::class)]
#[CoversClass(Labels::class)]
#[CoversClass(MultivalueCallbacks::class)]
#[CoversClass(MultivalueWidgetBuilder::class)]
#[CoversClass(RenderChildren::class)]
#[CoversClass(RequiredMarker::class)]
#[CoversClass(SavedInput::class)]
#[CoversClass(SchemaPreparer::class)]
#[CoversClass(SchemaTypes::class)]
#[CoversClass(UnsupportedElement::class)]
#[CoversClass(WidgetButton::class)]
#[CoversClass(WidgetState::class)]
#[CoversClass(WidgetStateStorage::class)]
#[RunTestsInSeparateProcesses]
final class SchemaLabelRenderingTest extends SchemaFormsKernelTestBase {

  use ConstantSchemas;

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
   * Constant markup escapes text and JSON, labels it and renders no input.
   */
  #[DataProvider('renderedConstantProvider')]
  public function testRenderedConstants(mixed $value, string $text): void {
    $form = (new FormGeneratorDrupal())->transform(self::constantSchema($value));
    $html = (string) $this->container->get('renderer')->renderInIsolation($form['fixed']);
    $this->assertMatchesRegularExpression('@<label[^>]*>Fixed</label>\s*' . preg_quote(Html::escape($text), '@') . '\s*</div>@', $html);
    $this->assertStringNotContainsString('<input', $html);
  }

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
