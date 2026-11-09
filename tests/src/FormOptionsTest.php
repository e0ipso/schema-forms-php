<?php

declare(strict_types=1);

namespace SchemaForms\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SchemaForms\Exception\ExceptionInterface;
use SchemaForms\Exception\InvalidOptionException;
use SchemaForms\FormOptions;

/**
 * FormOptions holds the four options of a transform() call.
 */
#[CoversClass(FormOptions::class)]
final class FormOptionsTest extends TestCase {

  /**
   * Every option is optional and means "not set" by default.
   */
  public function testDefaults(): void {
    $options = new FormOptions();
    $this->assertSame([], $options->uiHints);
    $this->assertNull($options->formState);
    $this->assertSame([], $options->currentInput);
    $this->assertNull($options->namespace);
  }

  /**
   * The given values are kept as they are.
   */
  public function testValues(): void {
    $form_state = new \stdClass();
    $input = (object) ['name' => 'Ada'];
    $options = new FormOptions(['name' => ['ui:title' => 'Name']], $form_state, $input, 'settings');
    $this->assertSame(['name' => ['ui:title' => 'Name']], $options->uiHints);
    $this->assertSame($form_state, $options->formState);
    $this->assertSame($input, $options->currentInput);
    $this->assertSame('settings', $options->namespace);
  }

  /**
   * An empty namespace is refused when the options are built.
   */
  public function testEmptyNamespaceIsRefused(): void {
    try {
      new FormOptions(namespace: '');
      $this->fail('An empty namespace must be refused.');
    }
    catch (InvalidOptionException $e) {
      $this->assertInstanceOf(ExceptionInterface::class, $e);
      $this->assertInstanceOf(\InvalidArgumentException::class, $e);
      $this->assertSame('The "namespace" option must be a non-empty string.', $e->getMessage());
    }
  }

}
