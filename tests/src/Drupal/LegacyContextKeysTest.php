<?php

declare(strict_types=1);

namespace SchemaForms\Tests\Drupal;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use SchemaForms\Drupal\FormGeneratorDrupal;
use SchemaForms\Exception\InvalidOptionException;
use SchemaForms\FormOptions;
use SchemaForms\Tests\Fixture\TranslationContainer;
use Shaper\Util\Context;

/**
 * The deprecated context keys build the form the matching option builds.
 */
#[CoversClass(FormGeneratorDrupal::class)]
#[IgnoreDeprecations]
final class LegacyContextKeysTest extends TestCase {

  use TranslationContainer;

  /**
   * The notice every deprecated key triggers.
   */
  private const NOTICE = 'Passing the "ui_hints", "form_state", "current_input" or "namespace" context key to FormGeneratorDrupal::transform() is deprecated in schema_forms:3.2.0 and is removed from schema_forms:4.0.0. Pass a SchemaForms\FormOptions as the "options" context key instead. See https://github.com/e0ipso/schema-forms-php#upgrading';

  /**
   * The schema every case builds.
   */
  private const SCHEMA = '{"type":"object","properties":{"name":{"type":"string"},"tags":{"type":"array","items":{"type":"string"}}}}';

  /**
   * The "ui_hints" key gives the UI hints.
   */
  public function testUiHints(): void {
    $hints = ['name' => ['ui:title' => 'Your name']];
    $this->assertSameForm(['ui_hints' => $hints], new FormOptions(uiHints: $hints));
  }

  /**
   * The "form_state" key gives the form state that keeps the widget state.
   */
  public function testFormState(): void {
    $state = new FormState();
    $this->assertSameForm(['form_state' => $state], new FormOptions(formState: $state));
    $this->assertArrayHasKey('prop_storage', $state->getStorage());
  }

  /**
   * The "current_input" key gives the saved values.
   */
  public function testCurrentInput(): void {
    $input = ['name' => 'Ada', 'tags' => ['a', 'b']];
    $this->assertSameForm(['current_input' => $input], new FormOptions(currentInput: $input));
  }

  /**
   * The "namespace" key gives the namespace.
   */
  public function testNamespace(): void {
    $this->assertSameForm(['namespace' => 'settings'], new FormOptions(namespace: 'settings'));
  }

  /**
   * A context without options or deprecated keys gives the defaults.
   */
  public function testNoOptions(): void {
    $schema = json_decode(self::SCHEMA);
    Html::resetSeenIds();
    $expected = (new FormGeneratorDrupal())->transform($schema);
    Html::resetSeenIds();
    $this->assertEquals($expected, (new FormGeneratorDrupal())->transform($schema, new Context(['options' => 'not options'])));
  }

  /**
   * A deprecated namespace key that is not a non-empty string is refused.
   */
  #[DataProvider('invalidNamespaceProvider')]
  public function testInvalidNamespace(mixed $namespace): void {
    $this->expectUserDeprecationMessage(self::NOTICE);
    $this->expectException(InvalidOptionException::class);
    $this->expectExceptionMessage('The "namespace" context key must be a non-empty string.');
    (new FormGeneratorDrupal())->transform(json_decode(self::SCHEMA), new Context(['namespace' => $namespace]));
  }

  /**
   * Provides namespace context values that are not non-empty strings.
   *
   * @return array<string, array{mixed}>
   *   The test cases.
   */
  public static function invalidNamespaceProvider(): array {
    return ['empty' => [''], 'integer' => [7], 'array' => [['a']]];
  }

  /**
   * The "options" key wins over the deprecated keys.
   */
  public function testOptionsWin(): void {
    $schema = json_decode(self::SCHEMA);
    $context = new Context([
      'options' => new FormOptions(uiHints: ['name' => ['ui:title' => 'From options']]),
      'ui_hints' => ['name' => ['ui:title' => 'From the key']],
    ]);

    $form = (new FormGeneratorDrupal())->transform($schema, $context);

    $this->assertSame('From options', (string) $form['name']['#title']);
  }

  /**
   * Builds the form with a deprecated key and with the option, and compares.
   *
   * @param array<string, mixed> $legacy
   *   The deprecated context key and its value.
   * @param \SchemaForms\FormOptions $options
   *   The matching options.
   */
  private function assertSameForm(array $legacy, FormOptions $options): void {
    $this->expectUserDeprecationMessage(self::NOTICE);
    $schema = json_decode(self::SCHEMA);
    Html::resetSeenIds();
    $expected = (new FormGeneratorDrupal())->transform($schema, new Context(['options' => $options]));
    Html::resetSeenIds();
    $actual = (new FormGeneratorDrupal())->transform($schema, new Context($legacy));
    $this->assertEquals($expected, $actual);
  }

}
