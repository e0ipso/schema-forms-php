<?php

declare(strict_types=1);

namespace SchemaForms\Tests\Kernel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use SchemaForms\Drupal\FieldBuilder;
use SchemaForms\Drupal\FormGeneratorDrupal;
use SchemaForms\Drupal\RequiredMarker;

/**
 * Required properties show core's marker without Form API enforcing it.
 */
#[CoversClass(FieldBuilder::class)]
#[CoversClass(FormGeneratorDrupal::class)]
#[CoversClass(RequiredMarker::class)]
#[RunTestsInSeparateProcesses]
final class RequiredMarkerRenderingTest extends SchemaFormsKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system'];

  /**
   * The schema: required checkboxes at the root and controls in an object.
   */
  private const SCHEMA = '{"type":"object","required":["picks","flag","nested"],"properties":{
    "picks":{"type":"array","title":"Picks","items":{"enum":["a","b"]}},
    "flag":{"type":"boolean","title":"Flag"},
    "nested":{"type":"object","required":["text","choice","many","inner"],"properties":{
      "text":{"type":"string","title":"Text"},
      "choice":{"title":"Choice","enum":["x","y"]},
      "many":{"type":"array","title":"Many","items":{"enum":["p","q"]}},
      "inner":{"type":"object","title":"Inner","properties":{"z":{"type":"string"}}},
      "optional":{"type":"string","title":"Optional"}
    }}
  }}';

  /**
   * Every required property is marked, and blank values still pass.
   */
  public function testMarkersWithoutEnforcement(): void {
    $values = ['picks' => [], 'nested' => ['text' => '', 'choice' => 'x', 'many' => [], 'inner' => ['z' => ''], 'optional' => '']];

    $result = $this->submitSchemaForm(json_decode(self::SCHEMA), ['schema' => $values], [], 'schema');

    $this->assertSame([], $result->errors);
    $form = $result->form;
    $html = (string) $this->container->get('renderer')->renderInIsolation($form);
    foreach (['Picks', 'Choice', 'Many'] as $title) {
      $this->assertStringContainsString('<span class="fieldset-legend js-form-required form-required">' . $title . '</span>', $html);
    }
    $this->assertStringNotContainsString('<span class="form-required">', $html);
    $this->assertMatchesRegularExpression('@<label class="form-required option" for="edit-schema-flag">Flag</label>@', $html);
    $this->assertMatchesRegularExpression('@<label class="form-required" for="edit-schema-nested-text">Text</label>@', $html);
    $this->assertMatchesRegularExpression('@<label for="edit-schema-nested-optional">Optional</label>@', $html);
    $this->assertMatchesRegularExpression('@<summary[^>]*class="js-form-required form-required"[^>]*>Inner</summary>@', $html);
    $this->assertDoesNotMatchRegularExpression('@<input[^>]*required="required"@', $html);
  }

}
