<?php

namespace SchemaForms\Tests;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

/**
 * Tests contributor and release configuration.
 */
#[CoversNothing]
final class RepositoryConfigurationTest extends TestCase {

  /**
   * Returns the repository root.
   */
  private function root(): string {
    return dirname(__DIR__, 2);
  }

  /**
   * Exercise the real configured commit-msg task.
   */
  public function testConfiguredCommitConventions(): void {
    $config = Yaml::parseFile($this->root() . '/grumphp.yml');
    $conventions = $config['grumphp']['tasks']['git_commit_message']['type_scope_conventions'];
    $this->assertSame(['schema', 'form', 'drupal', 'api', 'deps', 'deps-dev'], $conventions['scopes']);
    $this->assertSame(['build', 'ci', 'chore', 'docs', 'feat', 'fix', 'perf', 'refactor', 'revert', 'style', 'test'], $conventions['types']);
    $message_file = tempnam(sys_get_temp_dir(), 'schema-commit-');
    $legacy_base = tempnam(sys_get_temp_dir(), 'schema-grumphp-');
    $legacy_file = $legacy_base . '.yml';
    rename($legacy_base, $legacy_file);
    try {
      foreach ($conventions['types'] as $type) {
        $this->assertCommitExit($message_file, $type . ': bump x', 0);
      }
      foreach ($conventions['scopes'] as $scope) {
        $this->assertCommitExit($message_file, 'build(' . $scope . '): bump x', 0);
      }
      $invalid = ['unsupported(deps): bump x', 'build(unsupported): bump x'];
      foreach ($invalid as $message) {
        $this->assertCommitExit($message_file, $message, 1);
      }
      // Reproduce the former unnamed YAML list: both negatives used to pass.
      $config['grumphp']['tasks']['git_commit_message']['type_scope_conventions'] = array_values($conventions);
      file_put_contents($legacy_file, Yaml::dump($config, 8));
      foreach ($invalid as $message) {
        $this->assertCommitExit($message_file, $message, 0, $legacy_file);
      }
    }
    finally {
      unlink($message_file);
      unlink($legacy_file);
    }
  }

  /**
   * Runs the commit-msg task on a message and checks its exit code.
   */
  private function assertCommitExit(string $file, string $message, int $expected, ?string $config = NULL): void {
    file_put_contents($file, $message . "\n");
    $command = [PHP_BINARY, $this->root() . '/vendor/bin/grumphp.phar', 'git:commit-msg', '--no-interaction'];
    if ($config !== NULL) {
      $command[] = '--config=' . $config;
    }
    $command[] = $file;
    $process = new Process($command, $this->root(), timeout: 30);
    $actual = $process->run();
    $this->assertSame($expected, $actual, $message . "\n" . $process->getOutput() . $process->getErrorOutput());
  }

  /**
   * Core code and its tests never reach into Drupal or the adapter.
   *
   * The adapter under src/Drupal depends on the core, never the reverse, so
   * the core can be used and tested without Drupal installed.
   */
  public function testCoreDoesNotDependOnDrupal(): void {
    $files = [
      ...glob($this->root() . '/src/*.php'),
      ...glob($this->root() . '/src/Exception/*.php'),
      ...glob($this->root() . '/tests/src/*Test.php'),
    ];
    $this->assertNotEmpty($files);
    foreach ($files as $file) {
      $names = array_filter(
        \PhpToken::tokenize(file_get_contents($file)),
        static fn (\PhpToken $token): bool => $token->is([T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED]),
      );
      foreach ($names as $name) {
        $this->assertDoesNotMatchRegularExpression('/^\\\\?(Drupal|SchemaForms\\\\Drupal)\\\\/', $name->text, $file);
      }
    }
  }

  /**
   * The Development section names the configured checks.
   */
  public function testDevelopmentDocumentsEveryTask(): void {
    $config = Yaml::parseFile($this->root() . '/grumphp.yml');
    $readme = file_get_contents($this->root() . '/README.md');
    $development = substr($readme, strpos($readme, '## Development'));
    foreach (array_keys($config['grumphp']['tasks']) as $task) {
      $this->assertStringContainsString('`' . $task . '`', $development);
    }
    $instructions = [
      'no `else`',
      'no `exit`',
      'no `var_dump` or `kint`',
      'composer validate --strict',
      'composer audit --no-dev --locked',
      'staged files',
      'commit-msg hook',
      'file filters skip',
    ];
    foreach ($instructions as $instruction) {
      $this->assertStringContainsString($instruction, $development);
    }
  }

  /**
   * Release configuration avoids persisted checkout tokens.
   */
  public function testReleaseSupplyChainConfiguration(): void {
    foreach (['php.yml', 'release.yml'] as $workflow) {
      $config = Yaml::parseFile($this->root() . '/.github/workflows/' . $workflow);
      $checkouts = 0;
      foreach ($config['jobs'] as $job) {
        foreach ($job['steps'] as $step) {
          if (str_starts_with($step['uses'] ?? '', 'actions/checkout@')) {
            $this->assertFalse($step['with']['persist-credentials']);
            $checkouts++;
          }
          $this->assertDoesNotMatchRegularExpression('/\bgit\s+push\b/', $step['run'] ?? '');
        }
      }
      $this->assertGreaterThan(0, $checkouts);
    }
    $release = Yaml::parseFile($this->root() . '/.github/workflows/release.yml');
    $plugins = [];
    foreach ($release['jobs']['tag']['steps'] as $step) {
      if (isset($step['with']['extra_plugins'])) {
        $plugins = preg_split('/\s+/', trim($step['with']['extra_plugins']));
      }
    }
    $this->assertNotEmpty($plugins);
    foreach ($plugins as $plugin) {
      $this->assertMatchesRegularExpression('/^\S+@\d+\.\d+\.\d+$/D', $plugin);
    }
    $text = file_get_contents($this->root() . '/.github/workflows/release.yml');
    $this->assertMatchesRegularExpression('/# Dependabot does not update these pins; bump them by hand\.\s+extra_plugins:/', $text);
    $this->assertStringNotContainsString('@semantic-release/git"', file_get_contents($this->root() . '/.releaserc'));
    foreach (['package.json', 'package-lock.json', 'yarn.lock', 'pnpm-lock.yaml'] as $file) {
      $this->assertFileDoesNotExist($this->root() . '/' . $file);
    }
  }

  /**
   * The compatibility runner preserves failures and cleans up after success.
   *
   * Disposable command stand-ins avoid installs and shared vendor mutations.
   */
  #[DataProvider('compatibilityExitProvider')]
  public function testCompatibilityRunnerExitAndCleanup(string $failure, int $expected): void {
    $directory = sys_get_temp_dir() . '/schema-runner-' . bin2hex(random_bytes(8));
    mkdir($directory);
    $php = $directory . '/php';
    $composer = $directory . '/composer';
    file_put_contents($php, <<<'SH'
#!/usr/bin/env bash
set -eu
case "$1" in
  */prepare-json-schema-5.php) mkdir -p "$2/runtime" && touch "$2/grumphp.yml" ;;
  */bootstrap.php) if [[ "$FIXTURE_FAILURE" == bootstrap ]]; then exit 24; fi ;;
  */grumphp.phar)
    # A container wrapper only sees a config path given as its own argument.
    config=''
    for argument in "$@"; do
      if [[ "$argument" == --config=* ]]; then exit 26; fi
      if [[ "$config" == next ]]; then config=$argument; fi
      if [[ "$argument" == --config ]]; then config=next; fi
    done
    if [[ ! -f "$config" ]]; then exit 27; fi
    if [[ "$FIXTURE_FAILURE" == tests ]]; then exit 25; fi ;;
esac
SH);
    file_put_contents($composer, <<<'SH'
#!/usr/bin/env bash
set -eu
if [[ "$FIXTURE_FAILURE" == install && "$1" == install ]]; then exit 23; fi
SH);
    chmod($php, 0700);
    chmod($composer, 0700);
    try {
      $process = new Process(['bash', $this->root() . '/scripts/test-json-schema-5.sh'], $this->root(), [
        'PHP_BINARY' => $php,
        'COMPOSER_BINARY' => $composer,
        'SCHEMA_COMPAT_TMPDIR' => $directory,
        'SCHEMA_COMPAT_KEEP' => '0',
        'FIXTURE_FAILURE' => $failure,
      ], timeout: 30);
      $this->assertSame($expected, $process->run(), $process->getErrorOutput());
      preg_match('/Compatibility workspace: (.+)/', $process->getOutput(), $match);
      $this->assertCount(2, $match);
      if ($expected === 0) {
        $this->assertDirectoryDoesNotExist($match[1]);
        return;
      }
      $this->assertDirectoryExists($match[1]);
      $this->assertStringContainsString('(exit ' . $expected . ')', $process->getErrorOutput());
    }
    finally {
      (new Filesystem())->remove($directory);
    }
  }

  /**
   * Data provider for testCompatibilityRunnerExitAndCleanup().
   */
  public static function compatibilityExitProvider(): array {
    return [
      'success' => ['', 0],
      'install failure' => ['install', 23],
      'bootstrap failure' => ['bootstrap', 24],
      'test failure' => ['tests', 25],
    ];
  }

  /**
   * Reject wrong versions and classes loaded outside the runtime.
   */
  #[DataProvider('compatibilityGuardProvider')]
  public function testCompatibilityBootstrapGuards(bool $wrong_version, string $message): void {
    $directory = sys_get_temp_dir() . '/schema-bootstrap-' . bin2hex(random_bytes(8));
    mkdir($directory);
    try {
      $prepare = new Process($this->prepareCommand($directory), $this->root(), timeout: 30);
      $this->assertSame(0, $prepare->run(), $prepare->getErrorOutput());
      $manifest = json_decode(file_get_contents($directory . '/runtime/composer.json'), TRUE);
      // The runner pins the lowest JSON Schema 5 release composer.json allows.
      $this->assertSame('5.3.0', $manifest['require']['justinrainbow/json-schema']);
      $project = json_decode(file_get_contents($this->root() . '/composer.json'), TRUE);
      $this->assertStringStartsWith('^5.3 ', $project['require']['justinrainbow/json-schema']);
      $this->assertArrayNotHasKey('require-dev', $manifest);
      $this->assertArrayNotHasKey('autoload', $manifest);
      mkdir($directory . '/runtime/vendor/composer', 0700, TRUE);
      $installed = require $this->root() . '/vendor/composer/installed.php';
      $installed['versions']['justinrainbow/json-schema']['version'] = $wrong_version ? '6.13.1.0' : '5.3.0.0';
      $installed['versions']['justinrainbow/json-schema']['install_path'] = $directory . '/runtime';
      file_put_contents($directory . '/runtime/vendor/composer/installed.php', '<?php return ' . var_export($installed, TRUE) . ';');
      file_put_contents($directory . '/runtime/vendor/autoload.php', '<?php return require ' . var_export($this->root() . '/vendor/autoload.php', TRUE) . ';');
      $bootstrap = new Process([PHP_BINARY, $directory . '/bootstrap.php'], $this->root(), timeout: 30);
      $this->assertNotSame(0, $bootstrap->run());
      $this->assertStringContainsString($message, $bootstrap->getOutput() . $bootstrap->getErrorOutput());
    }
    finally {
      (new Filesystem())->remove($directory);
    }
  }

  /**
   * The preparation script fails loudly on bad arguments and failed writes.
   */
  #[DataProvider('preparationFailureProvider')]
  public function testPreparationFailures(int $arguments, string $message): void {
    $directory = sys_get_temp_dir() . '/schema-prepare-' . bin2hex(random_bytes(8));
    // The work directory does not exist, so creating runtime/ inside it fails.
    $command = array_slice($this->prepareCommand($directory . '/missing'), 0, $arguments + 2);
    $process = new Process($command, $this->root(), timeout: 30);
    $this->assertNotSame(0, $process->run());
    $this->assertStringContainsString($message, $process->getOutput() . $process->getErrorOutput());
    $this->assertDirectoryDoesNotExist($directory);
  }

  /**
   * Data provider for testPreparationFailures().
   */
  public static function preparationFailureProvider(): array {
    return [
      'missing arguments' => [2, 'Usage: php scripts/prepare-json-schema-5.php'],
      'missing work directory' => [3, 'Cannot create '],
    ];
  }

  /**
   * Data provider for testCompatibilityBootstrapGuards().
   */
  public static function compatibilityGuardProvider(): array {
    return [
      'wrong version' => [TRUE, 'Expected isolated JSON Schema 5.3.0.'],
      'wrong class path' => [FALSE, 'Unexpected compatibility class path: JsonSchema\\Validator'],
    ];
  }

  /**
   * Builds the command that prepares a compatibility workspace.
   *
   * @param string $directory
   *   The work directory.
   *
   * @return list<string>
   *   The command and its arguments.
   */
  private function prepareCommand(string $directory): array {
    return [
      PHP_BINARY,
      $this->root() . '/scripts/prepare-json-schema-5.php',
      $directory,
      $this->root() . '/phpunit.xml',
      $this->root() . '/grumphp.yml',
    ];
  }

}
