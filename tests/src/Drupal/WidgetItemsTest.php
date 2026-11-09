<?php

declare(strict_types=1);

namespace SchemaForms\Tests\Drupal;

use Drupal\Core\Form\FormState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SchemaForms\Drupal\FormGeneratorDrupal;
use SchemaForms\Drupal\WidgetItems;
use SchemaForms\Drupal\WidgetState;
use SchemaForms\FormOptions;
use SchemaForms\Tests\Fixture\TranslationContainer;
use Shaper\Util\Context;

/**
 * Host code reads a widget's items by the schema path of its property.
 */
#[CoversClass(WidgetItems::class)]
final class WidgetItemsTest extends TestCase {

  use TranslationContainer;

  /**
   * The items of widgets at the root, in an object and in a list item.
   */
  public function testReadsBuiltWidgets(): void {
    $schema = json_decode('{"type":"object","properties":{
      "tags":{"type":"array","items":{"type":"string"}},
      "address":{"type":"object","properties":{"lines":{"type":"array","items":{"type":"string"}}}},
      "rows":{"type":"array","items":{"type":"object","properties":{"tags":{"type":"array","items":{"type":"string"}}}}}
    }}');
    $state = new FormState();
    $input = ['tags' => ['a', 'b'], 'address' => ['lines' => ['x']], 'rows' => [['tags' => ['p', 'q', 'r']]]];

    (new FormGeneratorDrupal())->transform($schema, new Context(['options' => new FormOptions(formState: $state, currentInput: $input)]));

    $this->assertEquals(new WidgetItems([0, 1], []), WidgetItems::read($state, ['tags']));
    $this->assertEquals(new WidgetItems([0], []), WidgetItems::read($state, ['address', 'lines']));
    $this->assertEquals(new WidgetItems([0, 1, 2], []), WidgetItems::read($state, ['rows', 0, 'tags']));
    $this->assertNull(WidgetItems::read($state, ['tags'], 'other'));
    $this->assertNull(WidgetItems::read($state, ['missing']));
    $this->assertNull(WidgetItems::read($state, []));
  }

  /**
   * Removal gaps stay in the deltas, which come back as a list.
   */
  public function testReadsStoredState(): void {
    $state = new FormState();
    WidgetState::store($state, WidgetState::keys(['tags'], 'tags', 'host'), [
      'items_indices' => [0 => 0, 2 => 2, 3 => 5],
      'array_parents' => ['wrapper', 'tags'],
      'next_delta' => 6,
    ]);

    $this->assertEquals(new WidgetItems([0, 2, 5], ['wrapper', 'tags']), WidgetItems::read($state, ['tags'], 'host'));
  }

}
