<?php

declare(strict_types=1);

namespace SchemaForms\Tests\Drupal;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * The Drupal adapter depends on the core one way, and has no cycles.
 */
#[CoversNothing]
final class AdapterDependencyTest extends TestCase {

  /**
   * Core classes the adapter may use although they are not public API.
   *
   * Each one is @internal so that consumers do not build on it, but the
   * adapter is part of this package and is meant to call it.
   */
  private const ENTRY_POINTS = [
    // Reads the "type" keyword of a schema.
    'SchemaForms\SchemaType',
    // Validates the input schema of transform().
    'SchemaForms\JsonSchemaFormValidator',
    // Casts submitted strings to the schema types.
    'SchemaForms\RecursiveTypeCaster',
  ];

  /**
   * The adapter imports only public or documented core entry points.
   */
  public function testCoreImports(): void {
    $allowed = [...self::ENTRY_POINTS, ...self::publicCoreClasses()];
    $imports = array_merge(...array_map(
      static fn (string $file): array => array_map(
        static fn (string $import): string => basename($file) . ': ' . $import,
        self::coreImports($file)
      ),
      self::adapterFiles()
    ));
    $refused = array_filter(
      $imports,
      static fn (string $import): bool => !in_array(substr($import, strpos($import, ': ') + 2), $allowed, TRUE)
    );

    $this->assertNotSame([], $imports);
    $this->assertSame([], array_values($refused));
  }

  /**
   * No adapter class depends on itself through other adapter classes.
   *
   * A "::class" constant is a name, not a dependency: the render arrays name
   * the Form API callbacks of FormGeneratorDrupal that way, and cached forms
   * keep those names, so the builders must spell them.
   */
  public function testNoCycles(): void {
    $files = self::adapterFiles();
    $classes = array_map(static fn (string $file): string => basename($file, '.php'), $files);
    $graph = array_combine($classes, array_map(
      static fn (string $file): array => self::adapterDependencies($file, $classes),
      $files
    ));

    $this->assertArrayHasKey('FormGeneratorDrupal', $graph);
    $this->assertSame([], self::cycles($graph));
  }

  /**
   * The cycle check finds a cycle through several classes.
   */
  public function testCycleDetection(): void {
    $graph = ['A' => ['B'], 'B' => ['C'], 'C' => ['A'], 'D' => ['A']];

    $this->assertSame([['A', 'B', 'C', 'A']], self::cycles($graph));
    $this->assertSame([], self::cycles(['A' => ['B'], 'B' => [], 'C' => ['A', 'B']]));
  }

  /**
   * Lists the adapter's source files.
   *
   * @return list<string>
   *   The paths.
   */
  private static function adapterFiles(): array {
    return glob(dirname(__DIR__, 3) . '/src/Drupal/*.php') ?: [];
  }

  /**
   * Lists the classes of the core marked as public API.
   *
   * @return list<string>
   *   The fully qualified class names.
   */
  private static function publicCoreClasses(): array {
    $root = dirname(__DIR__, 3) . '/src';
    $files = [...(glob($root . '/*.php') ?: []), ...(glob($root . '/Exception/*.php') ?: [])];
    $public = array_filter($files, static fn (string $file): bool => str_contains((string) file_get_contents($file), "\n * @api\n"));
    return array_values(array_map(
      static fn (string $file): string => 'SchemaForms\\' . strtr(substr($file, strlen($root) + 1, -4), '/', '\\'),
      $public
    ));
  }

  /**
   * Lists the core classes a file imports.
   *
   * @param string $file
   *   The path.
   *
   * @return list<string>
   *   The fully qualified names under SchemaForms but outside the adapter.
   */
  private static function coreImports(string $file): array {
    $names = array_map(
      static fn (\PhpToken $token): string => ltrim($token->text, '\\'),
      array_filter(
        \PhpToken::tokenize((string) file_get_contents($file)),
        static fn (\PhpToken $token): bool => $token->is([T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED])
      )
    );
    return array_values(array_unique(array_filter(
      $names,
      static fn (string $name): bool => str_starts_with($name, 'SchemaForms\\') && !str_starts_with($name . '\\', 'SchemaForms\\Drupal\\')
    )));
  }

  /**
   * Lists the adapter classes a file depends on.
   *
   * @param string $file
   *   The path.
   * @param list<string> $classes
   *   The short names of the adapter classes.
   *
   * @return list<string>
   *   The short names of the classes the file uses, without itself and
   *   without names that only appear as "::class" constants.
   */
  private static function adapterDependencies(string $file, array $classes): array {
    $tokens = array_values(array_filter(
      \PhpToken::tokenize((string) file_get_contents($file)),
      static fn (\PhpToken $token): bool => !$token->isIgnorable()
    ));
    $used = array_filter(
      array_keys($tokens),
      static fn (int $index): bool => $tokens[$index]->is(T_STRING)
        && in_array($tokens[$index]->text, $classes, TRUE)
        && !(($tokens[$index + 1] ?? NULL)?->is(T_DOUBLE_COLON) && ($tokens[$index + 2] ?? NULL)?->is(T_CLASS))
    );
    $names = array_map(static fn (int $index): string => $tokens[$index]->text, $used);
    return array_values(array_diff(array_unique($names), [basename($file, '.php')]));
  }

  /**
   * Finds the cycles of a dependency graph.
   *
   * @param array<string, list<string>> $graph
   *   The dependencies of each node.
   *
   * @return list<list<string>>
   *   One path per cycle found, from a node back to itself.
   */
  private static function cycles(array $graph): array {
    $cycles = [];
    $done = [];
    $visit = static function (string $node, array $path) use (&$visit, &$cycles, &$done, $graph): void {
      $position = array_search($node, $path, TRUE);
      if ($position !== FALSE) {
        $cycles[] = [...array_slice($path, $position), $node];
        return;
      }
      if (isset($done[$node])) {
        return;
      }
      array_map(static fn (string $next) => $visit($next, [...$path, $node]), $graph[$node] ?? []);
      $done[$node] = TRUE;
    };
    array_map(static fn (string $node) => $visit($node, []), array_keys($graph));
    return $cycles;
  }

}
