<?php

declare(strict_types=1);

namespace SchemaForms\Tests\Drupal;

use Drupal\Core\Render\Element;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SchemaForms\Drupal\ButtonNames;
use SchemaForms\Drupal\FormGeneratorDrupal;
use SchemaForms\Drupal\FormTreeBuilder;
use SchemaForms\Drupal\WidgetButton;
use SchemaForms\Tests\Fixture\TranslationContainer;
use Shaper\Util\Context;

/**
 * Multivalue button names are unique within a generated form.
 */
#[CoversClass(ButtonNames::class)]
#[CoversClass(WidgetButton::class)]
#[CoversClass(FormTreeBuilder::class)]
final class ButtonNamesTest extends TestCase {

  use TranslationContainer;

  /**
   * Colliding names change; unique and unambiguous ones stay.
   */
  public function testCollidingNames(): void {
    $array = ['type' => 'array', 'items' => ['type' => 'string']];
    $schema = json_decode(json_encode(['type' => 'object', 'properties' => [
      'tag-list' => $array,
      'tag_list' => $array,
      'tag' => ['type' => 'object', 'properties' => ['list' => $array]],
      'a-b' => $array,
      'a' => ['type' => 'object', 'properties' => ['b' => $array]],
      'grid' => ['type' => 'array', 'items' => $array],
      'grid-0' => $array,
      'other-name' => $array,
    ]]));
    $current = ['a-b' => ['x'], 'a' => ['b' => ['y']], 'grid' => [['z']], 'grid-0' => ['w']];
    $form = (new FormGeneratorDrupal())->transform($schema, new Context(['current_input' => $current]));
    $names = self::names($form);

    $this->assertSame($names, array_unique($names));
    $this->assertSame('tag:2dlist:add_more', $names['tag-list.add_more']);
    $this->assertSame('tag:5flist:add_more', $names['tag_list.add_more']);
    $this->assertSame('tag_list_add_more', $names['tag.list.add_more']);
    $this->assertSame('a:2db-0:remove', $names['a-b.0.remove_one']);
    $this->assertSame('a-b-0-remove', $names['a.b.0.remove_one']);
    $this->assertSame('grid-0-0-remove', $names['grid.0.removable_element.0.remove_one']);
    $this->assertSame('grid:2d0-0:remove', $names['grid-0.0.remove_one']);
    $this->assertSame('other_name_add_more', $names['other-name.add_more']);
    $this->assertSame('a_b_add_more', $names['a.b.add_more']);
    $this->assertSame('a:2db:add_more', $names['a-b.add_more']);
  }

  /**
   * A namespace starts every name and keeps containers apart.
   */
  public function testNamespacedNames(): void {
    $schema = json_decode('{"type":"object","properties":{"tag_list":{"type":"array","items":{"type":"string"}}}}');
    $names = array_map(
      static fn (string $namespace): array => self::names((new FormGeneratorDrupal())->transform($schema, new Context([
        'namespace' => $namespace,
        'current_input' => ['tag_list' => ['a']],
      ]))),
      ['left' => 'left', 'odd' => 'a-b::c']
    );
    $this->assertSame([
      'tag_list.0.remove_one' => 'left::tag:5flist-0:remove',
      'tag_list.add_more' => 'left::tag:5flist:add_more',
    ], $names['left']);
    $this->assertSame('a:2db:3a:3ac::tag:5flist:add_more', $names['odd']['tag_list.add_more']);
  }

  /**
   * A namespace that is not a non-empty string is refused.
   */
  #[DataProvider('invalidNamespaceProvider')]
  public function testInvalidNamespace(mixed $namespace): void {
    $schema = json_decode('{"type":"object","properties":{"a":{"type":"string"}}}');
    $this->expectException(\InvalidArgumentException::class);
    (new FormGeneratorDrupal())->transform($schema, new Context(['namespace' => $namespace]));
  }

  public static function invalidNamespaceProvider(): array {
    return ['empty' => [''], 'integer' => [1], 'array' => [['a']]];
  }

  /**
   * Collects the button names of a generated tree by element path.
   */
  private static function names(array $element, string $path = ''): array {
    $names = isset($element['#name']) ? [$path => $element['#name']] : [];
    foreach (Element::children($element) as $key) {
      $names += self::names($element[$key], ltrim($path . '.' . $key, '.'));
    }
    return $names;
  }

}
