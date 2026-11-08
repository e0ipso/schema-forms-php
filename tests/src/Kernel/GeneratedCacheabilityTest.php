<?php

declare(strict_types=1);

namespace SchemaForms\Tests\Kernel;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\RenderContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use SchemaForms\Drupal\FormGeneratorDrupal;
use SchemaForms\Drupal\MultivalueCallbacks;
use SchemaForms\Tests\Kernel\Form\GeneratedSchemaForm;

/**
 * Generated forms depend only on the caller's input, so they cache fully.
 *
 * Nothing the generator builds reads the user, the request or the time. The
 * one cache context that appears comes from core: an #ajax button points at
 * the current route, so Drupal adds the "route" context to it.
 */
#[CoversClass(FormGeneratorDrupal::class)]
#[CoversClass(MultivalueCallbacks::class)]
#[RunTestsInSeparateProcesses]
final class GeneratedCacheabilityTest extends SchemaFormsKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system'];

  /**
   * A schema with every kind of control except the multivalue widget.
   */
  private const STATIC_SCHEMA = '{"type":"object","required":["picks","text"],"properties":{
    "text":{"type":"string"},
    "flag":{"type":"boolean"},
    "count":{"type":"integer"},
    "choice":{"enum":["a","b"]},
    "picks":{"type":"array","items":{"enum":["x","y"]}},
    "fixed":{"const":"same"},
    "nothing":{"type":"null"},
    "profile":{"type":"object","required":["name"],"properties":{"name":{"type":"string"}}}
  }}';

  /**
   * The same schema with a multivalue widget, whose buttons use AJAX.
   */
  private const AJAX_SCHEMA = '{"type":"object","properties":{
    "text":{"type":"string"},
    "items":{"type":"array","items":{"type":"string"}},
    "rows":{"type":"array","items":{"type":"object","properties":{"cell":{"type":"string"}}}}
  }}';

  /**
   * Controls without AJAX bubble no cache metadata at all.
   */
  public function testStaticControlsAddNoMetadata(): void {
    $form = $this->browserSubmit(new GeneratedSchemaForm(json_decode(self::STATIC_SCHEMA), parentKey: 'schema', tree: TRUE))->form;

    $metadata = $this->renderedMetadata($form['schema']);

    $this->assertSame(Cache::PERMANENT, $metadata->getCacheMaxAge());
    $this->assertSame([], $metadata->getCacheContexts());
    $this->assertSame([], $metadata->getCacheTags());
  }

  /**
   * Widgets add only the route context of core's AJAX buttons.
   */
  public function testAjaxWidgetsAddOnlyTheRouteContext(): void {
    $form = $this->browserSubmit(new GeneratedSchemaForm(json_decode(self::AJAX_SCHEMA), parentKey: 'schema', tree: TRUE, currentInput: ['items' => ['a']]))->form;

    $metadata = $this->renderedMetadata($form['schema']);

    $this->assertSame(Cache::PERMANENT, $metadata->getCacheMaxAge());
    $this->assertSame(['route'], $metadata->getCacheContexts());
    $this->assertSame([], $metadata->getCacheTags());
  }

  /**
   * The widgets the AJAX responses insert carry the same metadata.
   */
  public function testAjaxContentAddsNoMetadata(): void {
    $object = new GeneratedSchemaForm(json_decode(self::AJAX_SCHEMA), currentInput: ['items' => ['a', 'b']], withSave: TRUE);
    $appended = $this->browserSubmit($object, [], NULL, 'items_add_more', 'Append an item');
    $appended->formState->setRequestMethod('POST');
    $rebuilt = $this->container->get('form_builder')->rebuildForm($object->getFormId(), $appended->formState);

    $widgets = [
      'appended' => MultivalueCallbacks::appendedWidget($rebuilt, $appended->formState),
      'remaining' => $rebuilt['items'],
    ];

    foreach ($widgets as $case => $widget) {
      $metadata = $this->renderedMetadata($widget);
      $this->assertSame(Cache::PERMANENT, $metadata->getCacheMaxAge(), $case);
      $this->assertSame(['route'], $metadata->getCacheContexts(), $case);
      $this->assertSame([], $metadata->getCacheTags(), $case);
    }
  }

  /**
   * Renders an element and returns the cache metadata it bubbles.
   *
   * @param array $element
   *   The element.
   *
   * @return \Drupal\Core\Render\BubbleableMetadata
   *   The bubbled metadata.
   */
  private function renderedMetadata(array $element): BubbleableMetadata {
    $renderer = $this->container->get('renderer');
    $context = new RenderContext();
    $renderer->executeInRenderContext($context, static fn () => $renderer->render($element));
    $metadata = $context->pop();
    $this->assertInstanceOf(BubbleableMetadata::class, $metadata);
    return $metadata;
  }

}
