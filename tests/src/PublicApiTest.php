<?php

declare(strict_types=1);

namespace SchemaForms\Tests;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * The README's public API list matches the @api tags in src/.
 */
#[CoversNothing]
final class PublicApiTest extends TestCase {

  /**
   * Every @api symbol is documented, and every documented symbol is @api.
   */
  public function testReadmeListsTheApiSymbols(): void {
    $documented = self::documentedSymbols();
    $tagged = array_keys(array_filter(self::symbolTags(), static fn (string $tag): bool => $tag === 'api'));
    $this->assertSame($tagged, $documented);
  }

  /**
   * Each symbol in src/ is either public API or marked internal.
   */
  public function testEverySymbolDeclaresItsAudience(): void {
    $untagged = array_keys(array_filter(self::symbolTags(), static fn (string $tag): bool => $tag === ''));
    $this->assertSame([], $untagged);
  }

  /**
   * Reads the symbols listed in the README's Public API section.
   *
   * @return list<string>
   *   The fully qualified names, sorted.
   */
  private static function documentedSymbols(): array {
    $readme = (string) file_get_contents(dirname(__DIR__, 2) . '/README.md');
    $found = preg_match('/^### Public API\n(.*?)^#/ms', $readme, $section);
    self::assertSame(1, $found, 'README.md has no "### Public API" section.');
    preg_match_all('/^- `(SchemaForms\\\\[^`]+)`/m', $section[1], $matches);
    $symbols = $matches[1];
    self::assertNotEmpty($symbols);
    sort($symbols);
    return $symbols;
  }

  /**
   * Reads the audience tag of every class and interface in src/.
   *
   * @return array<string, string>
   *   'api', 'internal' or '' for each fully qualified name.
   */
  private static function symbolTags(): array {
    $src = dirname(__DIR__, 2) . '/src';
    $tags = [];
    $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
      $relative = substr($file->getPathname(), strlen($src) + 1, -strlen('.php'));
      $symbol = 'SchemaForms\\' . str_replace('/', '\\', $relative);
      $comment = (string) (new \ReflectionClass($symbol))->getDocComment();
      $tags[$symbol] = match (TRUE) {
        str_contains($comment, '@api') => 'api',
        str_contains($comment, '@internal') => 'internal',
        default => '',
      };
    }
    ksort($tags);
    return $tags;
  }

}
