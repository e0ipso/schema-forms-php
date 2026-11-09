<?php

declare(strict_types=1);

namespace SchemaForms\Tests\Fixture;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Language\LanguageDefault;
use Drupal\Core\StringTranslation\TranslationManager;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;

/**
 * Lets unit tests turn translatable markup into text.
 *
 * Without a container, casting TranslatableMarkup to a string makes Drupal's
 * ToStringTrait call exit() with status 0. A failing assertion that prints
 * such markup would then end PHPUnit as if every test had passed.
 */
trait TranslationContainer {

  /**
   * Installs a container whose translator returns the source strings.
   */
  #[Before]
  protected function setUpTranslationContainer(): void {
    $container = new ContainerBuilder();
    $container->set('string_translation', new TranslationManager(new LanguageDefault(['id' => 'en'])));
    \Drupal::setContainer($container);
  }

  /**
   * Removes the container, so no state leaks into the next test.
   */
  #[After]
  protected function tearDownTranslationContainer(): void {
    \Drupal::unsetContainer();
  }

}
