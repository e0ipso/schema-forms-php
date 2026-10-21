<?php

namespace SchemaForms\Tests\Fixture;

/**
 * Counts retrieval attempts while serving harmless, disposable schema content.
 *
 * Native file access is forwarded for autoloading and bundled metadata. HTTP
 * wrappers never contact a host. All wrappers are restored by observe().
 */
final class ReferenceSentinel {

  public $context;
  public static array $reads = [];
  private static string $target;
  private $stream;

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

  public function url_stat($path, $flags): array|false {
    return self::native(static fn () => @stat($path));
  }

  public function stream_read($count): string|false {
    return fread($this->stream, $count);
  }

  public function stream_eof(): bool {
    return feof($this->stream);
  }

  public function stream_stat(): array|false {
    return fstat($this->stream);
  }

  public function stream_seek($offset, $whence = SEEK_SET): bool {
    return fseek($this->stream, $offset, $whence) === 0;
  }

  public function stream_tell(): int|false {
    return ftell($this->stream);
  }

  public function stream_set_option($option, $arg1, $arg2): bool {
    return FALSE;
  }

  public function stream_close(): void {
    fclose($this->stream);
  }

}
