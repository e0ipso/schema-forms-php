<?php

declare(strict_types=1);

namespace SchemaForms\Tests\Drupal\SchemaForms;

use Drupal\Core\Form\FormState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use SchemaForms\Drupal\FormGeneratorDrupal;
use SchemaForms\FormOptions;
use SchemaForms\Tests\Fixture\TranslationContainer;
use Shaper\Util\Context;

/**
 * The deprecated Remove callbacks act like the ones generated forms name.
 *
 * LegacyCallbackSubmissionTest submits a whole form through the others, and
 * WidgetAjaxResponseTest compares the AJAX responses.
 */
#[CoversClass(FormGeneratorDrupal::class)]
#[IgnoreDeprecations]
final class DeprecatedCallbacksTest extends TestCase {

  use TranslationContainer;

  /**
   * Remove through the deprecated names.
   */
  public function testRemoveCallbacks(): void {
    $this->expectNotice('removeOneSubmit', 'MultivalueCallbacks::removeSubmit()');
    $this->expectNotice('removeOneAjax', 'MultivalueCallbacks::removeAjax()');
    $schema = json_decode('{"type":"object","properties":{"items":{"type":"array","items":{"type":"string"}}}}');
    $state = new FormState();
    $context = new Context(['options' => new FormOptions(formState: $state, currentInput: ['items' => ['a', 'b']])]);
    $form = (new FormGeneratorDrupal())->transform($schema, $context);
    $state->setTriggeringElement($form['items'][0]['remove_one'] + ['#array_parents' => ['items', 0, 'remove_one']]);

    FormGeneratorDrupal::removeOneSubmit($form, $state);

    $this->assertTrue($state->isRebuilding());
    $this->assertSame([1], array_values($state->getStorage()['prop_storage']['#parents']['items']['#props']['items']['items_indices']));
    // Without a Remove button, the response holds no command to render.
    $state->setTriggeringElement([]);
    $this->assertSame([], FormGeneratorDrupal::removeOneAjax($form, $state)->getCommands());
  }

  /**
   * Expects the notice of a deprecated generator method.
   *
   * @param string $method
   *   The method name.
   * @param string $replacement
   *   The callback generated forms name instead.
   */
  private function expectNotice(string $method, string $replacement): void {
    $this->expectUserDeprecationMessage(sprintf(
      'FormGeneratorDrupal::%s() is deprecated in schema_forms:3.2.0 and is removed from schema_forms:4.0.0. Generated forms name %s instead. See https://github.com/e0ipso/schema-forms-php#upgrading',
      $method,
      $replacement
    ));
  }

}
