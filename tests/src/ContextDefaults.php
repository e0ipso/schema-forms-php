<?php

declare(strict_types=1);

namespace SchemaForms\Tests;

use SchemaForms\FormTransformationInterface;
use Shaper\Util\Context;

/**
 * Adds default context values to every form a generator builds.
 *
 * The README shows this decorator. Values the caller passes win over the
 * defaults, and whatever the wrapped generator returns or throws reaches the
 * caller unchanged.
 */
final class ContextDefaults implements FormTransformationInterface {

  /**
   * Wraps a generator.
   *
   * @param \SchemaForms\FormTransformationInterface $inner
   *   The generator to wrap.
   * @param array<string, mixed> $defaults
   *   The context values to use when the caller passes none.
   */
  public function __construct(
    private readonly FormTransformationInterface $inner,
    private readonly array $defaults,
  ) {}

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function transform($data, ?Context $context = NULL) {
    $values = $context?->getArrayCopy() ?? [];
    return $this->inner->transform($data, new Context($values + $this->defaults));
  }

}
