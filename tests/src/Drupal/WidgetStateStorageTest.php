<?php

declare(strict_types=1);

namespace SchemaForms\Tests\Drupal;

use Drupal\Core\Form\FormState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SchemaForms\Drupal\WidgetLocation;
use SchemaForms\Drupal\WidgetState;
use SchemaForms\Drupal\WidgetStateStorage;

/**
 * Widget states live at keys host code can rely on.
 */
#[CoversClass(WidgetLocation::class)]
#[CoversClass(WidgetState::class)]
#[CoversClass(WidgetStateStorage::class)]
final class WidgetStateStorageTest extends TestCase {

  /**
   * Each location has its own keys, and a namespace keeps them apart.
   */
  public function testStorageKeys(): void {
    $form_state = new FormState();
    $storage = new WidgetStateStorage($form_state);
    $plain = new WidgetLocation(['tags'], 'tags');
    $namespaced = new WidgetLocation(['tags'], 'tags', 'host');

    $storage->save($plain, new WidgetState([0, 1]));
    $storage->set($namespaced, ['items_indices' => [3]]);

    $this->assertSame([
      'prop_storage' => [
        '#parents' => ['tags' => ['#props' => ['tags' => ['items_indices' => [0, 1], 'array_parents' => []]]]],
        '#namespaces' => ['host' => ['#parents' => ['tags' => ['#props' => ['tags' => ['items_indices' => [3]]]]]]],
      ],
    ], $form_state->getStorage());
    $this->assertSame([3], $storage->load($namespaced)->deltas());
    $this->assertNull($storage->get(new WidgetLocation(['other'], 'tags')));
    $this->assertNull($storage->load(new WidgetLocation(['other'], 'tags'))->itemsIndices);
  }

  /**
   * A widget's recorded location is read with wrong types treated as absent.
   */
  public function testLocationOfWidget(): void {
    $location = WidgetLocation::ofWidget([
      '#prop_parents' => ['a', 1],
      '#prop_name' => 7,
      '#schema_namespace' => 'host',
    ]);
    $broken = WidgetLocation::ofWidget([
      '#prop_parents' => 'a',
      '#prop_name' => ['a'],
      '#schema_namespace' => 5,
    ]);

    $this->assertEquals(new WidgetLocation(['a', 1], '7', 'host'), $location);
    $this->assertEquals(new WidgetLocation([], ''), $broken);
  }

}
