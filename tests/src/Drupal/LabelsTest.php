<?php

declare(strict_types=1);

namespace SchemaForms\Tests\Drupal;

use Drupal\Core\Template\Attribute;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SchemaForms\Drupal\FormGeneratorDrupal;
use SchemaForms\Drupal\Labels;
use SchemaForms\Tests\Fixture\TranslationContainer;
use Shaper\Util\Context;

/**
 * Multivalue items and their Remove buttons get names a reader can use.
 */
#[CoversClass(Labels::class)]
final class LabelsTest extends TestCase {

  use TranslationContainer;

  /**
   * A widget title with markup names the Remove button by its text.
   *
   * Attributes hold text, and Drupal's Attribute turns markup into plain
   * text, so the name is not read out tag by tag. The hidden item title is
   * HTML and keeps the markup.
   */
  public function testRemoveButtonNameIsText(): void {
    $schema = json_decode('{"type":"object","properties":{"tags":{"type":"array","items":{"type":"string"}}}}', flags: JSON_THROW_ON_ERROR);
    $form = (new FormGeneratorDrupal())->transform($schema, new Context([
      'ui_hints' => ['tags' => ['ui:title' => '<em>Tags</em> &amp; labels']],
      'current_input' => ['tags' => ['a']],
    ]));

    $item = $form['tags'][0];
    $attributes = (string) new Attribute($item['remove_one']['#attributes']);

    $this->assertStringContainsString('aria-label="Remove Tags &amp; labels (value 1)"', $attributes);
    $this->assertStringContainsString('title="Remove Tags &amp; labels (value 1)"', $attributes);
    $this->assertSame('<em>Tags</em> &amp; labels (value 1)', (string) $item['removable_element']['#title']);
  }

  /**
   * A title without a string form leaves the placeholder empty.
   */
  public function testTitleWithoutStringForm(): void {
    $this->assertSame(' (value 2)', (string) Labels::itemTitle(['not', 'a', 'title'], 2));
    $this->assertSame('Remove  (value 2)', (string) Labels::removeButtonName(new \stdClass(), 2));
  }

}
