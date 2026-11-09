<?php

declare(strict_types=1);

namespace SchemaForms\Tests\Kernel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use SchemaForms\Drupal\FieldBuilder;
use SchemaForms\Drupal\FormGeneratorDrupal;
use SchemaForms\Tests\Kernel\Form\GeneratedSchemaForm;

/**
 * Controls hidden with "ui:visible" keep their saved values on Save.
 */
#[CoversClass(FieldBuilder::class)]
#[CoversClass(FormGeneratorDrupal::class)]
#[RunTestsInSeparateProcesses]
final class HiddenControlSubmissionTest extends SchemaFormsKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system'];

  /**
   * The schema, with one property of each kind of control.
   */
  private const SCHEMA = '{"type":"object","properties":{
    "text":{"type":"string"},
    "flag":{"type":"boolean"},
    "count":{"type":"integer"},
    "choice":{"type":"string","enum":["a","b"]},
    "picks":{"type":"array","items":{"type":"string","enum":["x","y"]}},
    "items":{"type":"array","items":{"type":"string"}},
    "profile":{"type":"object","properties":{"name":{"type":"string"}}},
    "shown":{"type":"string"}
  }}';

  /**
   * Hidden controls render nothing and submit their saved values.
   *
   * @param array<string, mixed> $edits
   *   Values a client posts for the hidden controls anyway.
   */
  #[DataProvider('editsProvider')]
  public function testHiddenValuesSurviveSave(array $edits): void {
    $saved = [
      'text' => 'keep',
      'flag' => TRUE,
      'count' => 7,
      'choice' => 'b',
      'picks' => ['y'],
      'items' => ['first', 'second'],
      'profile' => ['name' => 'Ada'],
      'shown' => 'old',
    ];
    $hidden = array_fill_keys(array_diff(array_keys($saved), ['shown']), ['ui:visible' => 0]);
    $object = new GeneratedSchemaForm(json_decode(self::SCHEMA), $hidden, parentKey: 'schema', tree: TRUE, currentInput: $saved, withSave: TRUE);

    $result = $this->browserSubmit($object, ['shown' => 'new'] + $edits, 'schema');

    $this->assertSame([], $result->errors);
    $this->assertSame(array_replace($saved, ['shown' => 'new']), $result->values['schema']);
    $form = $result->form;
    $html = (string) $this->container->get('renderer')->renderInIsolation($form);
    $this->assertStringContainsString('name="schema[shown]"', $html);
    $this->assertStringNotContainsString('name="schema[text]"', $html);
    $this->assertStringNotContainsString('schema[items]', $html);
  }

  /**
   * Lists submissions with and without values for the hidden controls.
   *
   * @return array<string, array{0: array<string, mixed>}>
   *   The edits, keyed by case name.
   */
  public static function editsProvider(): array {
    return [
      'untouched' => [[]],
      'tampered' => [['text' => 'changed', 'count' => '9', 'choice' => 'a', 'items.0.removable_element' => 'x']],
    ];
  }

}
