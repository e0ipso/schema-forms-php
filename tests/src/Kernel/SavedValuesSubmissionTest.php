<?php

declare(strict_types=1);

namespace SchemaForms\Tests\Kernel;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormState;
use Drupal\Core\Render\Element;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use SchemaForms\Drupal\FormGeneratorDrupal;
use SchemaForms\Drupal\FormValidatorDrupal;
use SchemaForms\Tests\Kernel\Form\GeneratedSchemaForm;

/**
 * Saved values survive an actual Save rather than an implicit Append submit.
 */
#[CoversClass(FormGeneratorDrupal::class)]
#[CoversClass(FormValidatorDrupal::class)]
#[RunTestsInSeparateProcesses]
final class SavedValuesSubmissionTest extends SchemaFormsKernelTestBase {

  protected static $modules = ['system'];

  private const MAX_ITEMS = 2;

  private const SCHEMA = '{"type":"object","properties":{
    "name":{"type":["string","null"],"default":"default"},
    "flag":{"type":["boolean","null"],"default":true},
    "count":{"type":["integer","null"],"default":3},
    "choice":{"type":["string","null"],"enum":["a","b",null],"default":"a"},
    "tags":{"type":["array","null"],"default":["default"],"items":{"type":"string"}},
    "picks":{"type":["array","null"],"items":{"type":"string","enum":["x","y"]},"default":["x"]},
    "profile":{"type":["object","null"],"default":{"name":"default"},"properties":{"name":{"type":"string","default":"child"},"on":{"type":"boolean"}}},
    "raw":{"type":["array","null"],"default":["default"]},
    "items":{"type":"array","items":{"type":["string","null"]}},
    "people":{"type":"array","items":{"type":["object","null"],"properties":{"name":{"type":"string"}}}},
    "grid":{"type":"array","items":{"type":["array","null"],"items":{"type":"string"}}}
  }}';

  /**
   * Collects successful browser controls using Drupal's final input paths.
   */
  private function browserValues(array $element, array &$values): void {
    foreach (Element::children($element) as $key) {
      $child = $element[$key];
      if (($child['#access'] ?? TRUE) === FALSE || !empty($child['#disabled'])) {
        continue;
      }
      $type = $child['#type'] ?? '';
      if (in_array($type, ['submit', 'button', 'radio'], TRUE)) {
        continue;
      }
      if (empty($child['#input']) || $type === 'checkboxes') {
        $this->browserValues($child, $values);
        continue;
      }
      if ($type === 'checkbox') {
        if (!empty($child['#value'])) {
          NestedArray::setValue($values, $child['#parents'], $child['#return_value']);
        }
        continue;
      }
      if ($type === 'radios' && ($child['#value'] ?? '') === '') {
        continue;
      }
      NestedArray::setValue($values, $child['#parents'], $child['#value'] ?? '');
    }
  }

  /**
   * Builds once, then submits the controls shown to the user with Save.
   */
  private function save(object $schema, array $current, array $edits = [], ?string $parent = NULL, string $button = 'op'): SchemaFormSubmission {
    $request = $this->container->get('request_stack')->getCurrentRequest();
    $request->setMethod('POST');
    $request->attributes->set('_route', 'schema_forms_test');
    $request->attributes->set('_route_object', new \Symfony\Component\Routing\Route('/schema-form'));
    $object = new GeneratedSchemaForm($schema, parentKey: $parent, tree: TRUE, currentInput: $current, withSave: TRUE);
    $builder = $this->container->get('form_builder');
    $built = $builder->getForm($object);
    $values = [];
    $this->browserValues($built, $values);
    foreach ($edits as $path => $value) {
      NestedArray::setValue($values, [...($parent === NULL ? [] : [$parent]), ...explode('.', $path)], $value);
    }
    $values[$button] = $button === 'op' ? 'Save' : 'Append an item';
    $state = (new FormState())->setValues($values);
    $builder->submitForm($object, $state);
    if ($button === 'op') {
      $this->assertSame('Save', (string) $state->getTriggeringElement()['#value']);
    }
    return new SchemaFormSubmission($state->getCompleteForm(), $state, array_map('strval', $state->getErrors()), $state->getValues());
  }

  #[DataProvider('placementProvider')]
  public function testUntouchedNullAtEveryPath(?string $parent): void {
    $current = array_fill_keys(['name', 'flag', 'count', 'choice', 'tags', 'picks', 'profile', 'raw'], NULL);
    $current += ['items' => ['first', NULL], 'people' => [NULL], 'grid' => [NULL]];
    $result = $this->save(json_decode(self::SCHEMA), $current, parent: $parent);
    $this->assertSame([], $result->errors);
    $values = $parent === NULL ? $result->values : $result->values[$parent];
    foreach ($current as $key => $value) {
      $this->assertArrayHasKey($key, $values);
      $this->assertSame($value, $values[$key], $key);
    }
    $this->assertStringNotContainsString('SavedNull', serialize($result->values));
    $form = $parent === NULL ? $result->form : $result->form[$parent];
    $this->assertFalse($form['name']['#disabled'] ?? FALSE);
    $this->assertFalse($form['profile']['#disabled'] ?? FALSE);
    $this->assertSame('', $form['name']['#value']);
    $this->assertSame('', $form['profile']['name']['#value']);
  }

  public static function placementProvider(): array {
    return ['root' => [NULL], 'nested' => ['host']];
  }

  public function testEditsToNullAreSaved(): void {
    $current = array_fill_keys(['name', 'flag', 'count', 'choice', 'tags', 'picks', 'profile'], NULL);
    $current += ['items' => [NULL], 'people' => [NULL], 'grid' => [NULL]];
    $result = $this->save(json_decode(self::SCHEMA), $current, [
      'name' => '0', 'flag' => 1, 'count' => '0', 'choice' => 'b',
      'profile.name' => 'edited', 'picks.y' => 'y',
      'items.0.removable_element' => 'edited',
      'people.0.removable_element.name' => 'person',
    ], 'host');
    $this->assertSame([], $result->errors);
    $values = $result->values['host'];
    foreach (['name' => '0', 'flag' => TRUE, 'count' => 0, 'choice' => 'b', 'profile' => ['name' => 'edited', 'on' => FALSE], 'picks' => ['y'], 'items' => ['edited'], 'people' => [['name' => 'person']], 'grid' => [NULL]] as $key => $value) {
      $this->assertSame($value, $values[$key], $key);
    }
  }

  public function testExplicitEmptyAndNonemptyValues(): void {
    $current = ['name' => '', 'flag' => FALSE, 'count' => 0, 'choice' => 'b', 'tags' => [], 'picks' => [], 'profile' => ['name' => '', 'on' => FALSE], 'raw' => [], 'items' => []];
    foreach ([$current, ['name' => 'saved', 'flag' => TRUE, 'count' => 7, 'choice' => 'b', 'tags' => ['saved'], 'picks' => ['y'], 'profile' => ['name' => 'saved', 'on' => TRUE], 'raw' => ['saved'], 'items' => ['saved']]] as $saved) {
      $result = $this->save(json_decode(self::SCHEMA), $saved);
      $this->assertSame([], $result->errors);
      foreach ($saved as $key => $value) {
        $this->assertSame($value, $result->values[$key], $key);
      }
    }
  }

  public function testAbsentValuesReceiveDefaults(): void {
    $result = $this->save(json_decode(self::SCHEMA), []);
    $this->assertSame([], $result->errors);
    foreach (['name' => 'default', 'flag' => TRUE, 'count' => 3, 'choice' => 'a', 'tags' => ['default'], 'picks' => ['x'], 'profile' => ['name' => 'default', 'on' => FALSE], 'raw' => ['default']] as $key => $value) {
      $this->assertSame($value, $result->values[$key], $key);
    }
  }

  #[DataProvider('finiteItemsProvider')]
  public function testFiniteWidgetThroughFormBuilder(array $saved): void {
    $schema = json_decode('{"type":"object","properties":{"a":{"type":"array","maxItems":2,"items":{"type":"string"}},"b":{"type":"array","items":{"type":"string"}}}}');
    $result = $this->save($schema, ['a' => $saved, 'b' => ['other']], parent: 'host');
    $this->assertSame($saved, $result->values['host']['a']);
    $this->assertSame(['other'], $result->values['host']['b']);
    $this->assertSame(count($saved) > self::MAX_ITEMS, $result->errors !== []);
    $widget = $result->form['host']['a'];
    $this->assertSame(count($saved) < self::MAX_ITEMS, $widget['add_more']['#access'] ?? TRUE);
    $this->assertSame([['host', 'a']], $widget['add_more']['#limit_validation_errors']);
    foreach (array_filter(Element::children($widget), 'is_int') as $delta) {
      $this->assertSame(['host', 'a', $delta, 'removable_element'], $widget[$delta]['removable_element']['#parents']);
      $this->assertSame($widget[$delta]['#wrapper_id'], $widget[$delta]['remove_one']['#ajax']['wrapper']);
      $this->assertStringContainsString($widget[$delta]['#wrapper_id'], $widget[$delta]['#prefix']);
    }
  }

  public static function finiteItemsProvider(): array {
    return ['empty' => [[]], 'below maximum' => [['first']], 'at maximum' => [['first', 'second']], 'oversized saved data' => [['first', 'second', 'third']]];
  }

  #[DataProvider('fixedArrayProvider')]
  public function testUnsupportedArraySurvivesSave(bool $tuple, bool $saved): void {
    $property = (object) ['type' => 'array', 'default' => ['default']];
    if ($tuple) {
      $property->items = [(object) ['type' => 'string']];
    }
    $schema = (object) ['type' => 'object', 'properties' => (object) ['value' => $property]];
    $result = $this->save($schema, $saved ? ['value' => ['saved']] : []);
    $this->assertSame([], $result->errors);
    $this->assertSame($saved ? ['saved'] : ['default'], $result->values['value']);
    $this->assertSame('item', $result->form['value']['#type']);
    $this->assertTrue($result->form['value']['#disabled']);
  }

  public static function fixedArrayProvider(): array {
    return ['missing default' => [FALSE, FALSE], 'missing saved' => [FALSE, TRUE], 'tuple default' => [TRUE, FALSE], 'tuple saved' => [TRUE, TRUE]];
  }

  public function testAppendingNullArrayStartsEditableItem(): void {
    $schema = json_decode(self::SCHEMA);
    $result = $this->save($schema, ['tags' => NULL, 'people' => [NULL]], button: 'tags_add_more');
    $this->assertTrue($result->formState->isRebuilding());
    $this->assertSame([0], FormGeneratorDrupal::getPropFormState(['tags'], 'tags', $result->formState)['items_indices']);
    $this->assertStringNotContainsString('SavedNull', serialize($result->values));
    $result->formState->setRequestMethod('POST');
    $rebuilt = $this->container->get('form_builder')->rebuildForm('schema_forms_generated_schema_form', $result->formState);
    $this->assertArrayHasKey(0, $rebuilt['tags']);
    $this->assertFalse($rebuilt['tags'][0]['removable_element']['#disabled'] ?? FALSE);
  }

  public function testFiniteAppendRebuildsFinalPaths(): void {
    $schema = json_decode('{"type":"object","properties":{"a":{"type":"array","maxItems":2,"items":{"type":"string"}},"b":{"type":"array","items":{"type":"string"}}}}');
    $result = $this->save($schema, ['a' => ['first'], 'b' => ['other']], button: 'a_add_more');
    $this->assertSame([], $result->errors);
    $this->assertTrue($result->formState->isRebuilding());
    $this->assertSame([0, 1], FormGeneratorDrupal::getPropFormState(['a'], 'a', $result->formState)['items_indices']);
    $result->formState->setRequestMethod('POST');
    $form = $this->container->get('form_builder')->rebuildForm('schema_forms_generated_schema_form', $result->formState);
    $this->assertFalse($form['a']['add_more']['#access']);
    $this->assertSame([['a']], $form['a']['add_more']['#limit_validation_errors']);
    $this->assertSame(['a', 1, 'removable_element'], $form['a'][1]['removable_element']['#parents']);
    $this->assertNotSame($form['a'][0]['#wrapper_id'], $form['a'][1]['#wrapper_id']);
    $this->assertNotSame($form['a'][1]['#wrapper_id'], $form['b'][0]['#wrapper_id']);
    $result->formState->setTriggeringElement($form['a']['add_more']);
    $ajax = FormGeneratorDrupal::addMoreAjax($form, $result->formState);
    $this->assertFalse($ajax['add_more']['#access']);
    $this->assertStringContainsString('ajax-new-content', $ajax[1]['#prefix']);
    $this->assertSame($ajax[1]['#wrapper_id'], $ajax[1]['remove_one']['#ajax']['wrapper']);
  }

}
