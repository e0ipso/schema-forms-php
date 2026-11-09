<?php

declare(strict_types=1);

namespace SchemaForms\Tests\Drupal;

use Drupal\Core\Form\FormState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SchemaForms\Drupal\WidgetState;

/**
 * Widget states live at keys host code can rely on.
 */
#[CoversClass(WidgetState::class)]
final class WidgetStateTest extends TestCase {

  /**
   * Each widget has its own keys, and a namespace keeps them apart.
   */
  public function testStorageKeys(): void {
    $form_state = new FormState();
    $plain = WidgetState::keys(['tags'], 'tags');
    $namespaced = WidgetState::keys(['tags'], 'tags', 'host');

    (new WidgetState([0, 1]))->save($form_state, $plain);
    WidgetState::store($form_state, $namespaced, ['items_indices' => [3]]);

    $this->assertSame([
      'prop_storage' => [
        '#parents' => ['tags' => ['#props' => ['tags' => ['items_indices' => [0, 1], 'array_parents' => []]]]],
        '#namespaces' => ['host' => ['#parents' => ['tags' => ['#props' => ['tags' => ['items_indices' => [3]]]]]]],
      ],
    ], $form_state->getStorage());
    $this->assertSame([3], WidgetState::load($form_state, $namespaced)->deltas());
    $this->assertNull(WidgetState::stored($form_state, WidgetState::keys(['other'], 'tags')));
    $this->assertNull(WidgetState::load($form_state, WidgetState::keys(['other'], 'tags'))->itemsIndices);
  }

  /**
   * A widget's recorded keys are read with wrong types treated as absent.
   */
  public function testKeysOfWidget(): void {
    $keys = WidgetState::keysOf([
      '#prop_parents' => ['a', 1],
      '#prop_name' => 7,
      '#schema_namespace' => 'host',
    ]);
    $broken = WidgetState::keysOf([
      '#prop_parents' => 'a',
      '#prop_name' => ['a'],
      '#schema_namespace' => 5,
    ]);

    $this->assertSame(WidgetState::keys(['a', 1], '7', 'host'), $keys);
    $this->assertSame(['prop_storage', '#parents', '#props', ''], $broken);
  }

}
