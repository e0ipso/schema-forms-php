<?php

declare(strict_types=1);

namespace SchemaForms\Tests\Kernel;

use Drupal\Core\Form\FormState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use SchemaForms\Drupal\ElementDataPaths;
use SchemaForms\Drupal\ErrorElementMapper;
use SchemaForms\Drupal\FormGeneratorDrupal;
use SchemaForms\Drupal\MultivalueWidget;
use SchemaForms\Drupal\UserInputCleaner;

/**
 * Property names that look like list keys or widget keys are plain names.
 */
#[CoversClass(FormGeneratorDrupal::class)]
#[CoversClass(UserInputCleaner::class)]
#[CoversClass(ErrorElementMapper::class)]
#[CoversClass(ElementDataPaths::class)]
#[CoversClass(MultivalueWidget::class)]
#[RunTestsInSeparateProcesses]
final class PropertyNameSubmissionTest extends SchemaFormsKernelTestBase {

  protected static $modules = ['system'];

  /**
   * Integer-like property names keep their object.
   *
   * Regression: an object whose keys were all numeric became a list, so it
   * failed its own "type": "object".
   */
  public function testIntegerLikePropertyNames(): void {
    $schema = json_decode('{"type":"object","properties":{"codes":{"type":"object","properties":{"200":{"type":"string"},"404":{"type":"string"}}}}}', flags: JSON_THROW_ON_ERROR);
    $result = $this->submitSchemaForm($schema, ['schema' => ['codes' => ['200' => 'ok', '404' => 'missing']]], [], 'schema');
    $this->assertSame([], $result->errors);
    $this->assertSame([200 => 'ok', 404 => 'missing'], $result->values['schema']['codes']);
  }

  /**
   * Errors for properties named like widget parts land on their element.
   *
   * Regression: the error mapper skipped properties named add_more or
   * remove_one, and dropped the segment of one named removable_element.
   */
  #[DataProvider('widgetNameProvider')]
  public function testWidgetLikePropertyNameErrors(string $name, string $placement): void {
    $leaf = (object) ['type' => 'string', 'minLength' => 3];
    $property = $leaf;
    $input = [$name => 'x'];
    $saved = [];
    $state = new FormState();
    $path = ['schema', $name];
    if ($placement === 'nested') {
      $property = (object) ['type' => 'object', 'properties' => (object) [$name => $leaf]];
      $input = [$name => [$name => 'x']];
      $path[] = $name;
    }
    if ($placement === 'item') {
      $property = (object) ['type' => 'array', 'items' => (object) ['type' => 'object', 'properties' => (object) [$name => $leaf]]];
      $input = [$name => [0 => ['removable_element' => [$name => 'x']]]];
      $saved = [$name => [[$name => 'valid']]];
      $path = [...$path, 0, 'removable_element', $name];
    }
    $schema = (object) ['type' => 'object', 'properties' => (object) [$name => $property]];
    $result = $this->submitSchemaForm($schema, ['schema' => $input], [], 'schema', form_state: $state, current_input: $saved);
    $this->assertSame([implode('][', $path)], array_keys($result->errors));
    $this->assertStringContainsString('minLength', implode(' ', $result->errors));
  }

  public static function widgetNameProvider(): array {
    $cases = [];
    foreach (['add_more', 'remove_one', 'removable_element'] as $name) {
      foreach (['flat', 'nested', 'item'] as $placement) {
        $cases[$name . ' ' . $placement] = [$name, $placement];
      }
    }
    return $cases;
  }

}
