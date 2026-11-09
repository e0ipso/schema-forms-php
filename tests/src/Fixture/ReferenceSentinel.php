<?php

declare(strict_types=1);

namespace SchemaForms\Tests\Fixture;

/**
 * Counts retrieval attempts while serving harmless, disposable schema content.
 *
 * Native file access is forwarded for autoloading and bundled metadata. HTTP
 * wrappers never contact a host. All wrappers are restored by observe().
 */
final class ReferenceSentinel {

  // phpcs:disable Drupal.NamingConventions.ValidFunctionName.ScopeNotCamelCaps -- PHP calls stream wrapper methods by these names.

  /**
   * The stream context, which PHP sets on every wrapper instance.
   *
   * @var resource|null
   */
  public $context;

  /**
   * The paths whose retrieval was attempted during observe().
   *
   * @var list<string>
   */
  public static array $reads = [];

  /**
   * The file path observe() serves harmless content for.
   */
  private static string $target;

  /**
   * The stream this wrapper instance reads from.
   *
   * @var resource|false
   */
  private $stream;

  /**
   * Runs a callback with file and HTTP retrieval replaced by this wrapper.
   *
   * @param string $target
   *   The file path to count and serve harmless content for.
   * @param callable $callback
   *   The code to observe.
   *
   * @return mixed
   *   What the callback returns.
   */
  public static function observe(string $target, callable $callback): mixed {
    self::$target = $target;
    self::$reads = [];
    $replaced = [];
    try {
      foreach (['file', 'http', 'https'] as $scheme) {
        if (!stream_wrapper_unregister($scheme)) {
          throw new \RuntimeException('Cannot replace ' . $scheme);
        }
        $replaced[] = $scheme;
        if (!stream_wrapper_register($scheme, self::class)) {
          throw new \RuntimeException('Cannot register ' . $scheme);
        }
      }
      return $callback();
    }
    finally {
      foreach ($replaced as $scheme) {
        stream_wrapper_unregister($scheme);
        stream_wrapper_restore($scheme);
      }
    }
  }

  /**
   * Opens a counted path with harmless content, or any other path natively.
   */
  public function stream_open($path, $mode, $options, &$opened_path): bool {
    if ($path === self::$target || $path === 'file://' . self::$target || preg_match('@^https?://@', $path)) {
      self::$reads[] = $path;
      $this->stream = fopen('php://memory', 'r+');
      fwrite($this->stream, '{"type":"string","minLength":2}');
      rewind($this->stream);
      return TRUE;
    }
    $this->stream = self::native(static fn () => fopen($path, $mode));
    $opened_path = $path;
    return $this->stream !== FALSE;
  }

  /**
   * Runs a callback with the native file wrapper restored.
   *
   * @param callable $callback
   *   The file operation.
   *
   * @return mixed
   *   What the callback returns.
   */
  private static function native(callable $callback): mixed {
    stream_wrapper_restore('file');
    try {
      return $callback();
    }
    finally {
      stream_wrapper_unregister('file');
      stream_wrapper_register('file', self::class);
    }
  }

  /**
   * Reports file status from the native wrapper.
   */
  public function url_stat($path, $flags): array|false {
    return self::native(static fn () => @stat($path));
  }

  /**
   * Reads from the open stream.
   */
  public function stream_read($count): string|false {
    return fread($this->stream, $count);
  }

  /**
   * Tells whether the open stream is at its end.
   */
  public function stream_eof(): bool {
    return feof($this->stream);
  }

  /**
   * Reports the status of the open stream.
   */
  public function stream_stat(): array|false {
    return fstat($this->stream);
  }

  /**
   * Moves the position in the open stream.
   */
  public function stream_seek($offset, $whence = SEEK_SET): bool {
    return fseek($this->stream, $offset, $whence) === 0;
  }

  /**
   * Tells the position in the open stream.
   */
  public function stream_tell(): int|false {
    return ftell($this->stream);
  }

  /**
   * Refuses stream options, which the served content does not need.
   */
  public function stream_set_option($option, $arg1, $arg2): bool {
    return FALSE;
  }

  /**
   * Closes the open stream.
   */
  public function stream_close(): void {
    fclose($this->stream);
  }

}
