<?php

namespace SchemaForms\Tests;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

/**
 * Contributor and release configuration regressions from Wave 3.
 */
#[CoversNothing]
final class RepositoryConfigurationTest extends TestCase {

  private function root(): string {
    return dirname(__DIR__, 2);
  }

  /**
   * SCHE-0035/0063: exercise the real configured commit-msg task.
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
   * SCHE-0036: the Development section names the configured checks.
   */
  public function testDevelopmentDocumentsEveryTask(): void {
    $config = Yaml::parseFile($this->root() . '/grumphp.yml');
    $readme = file_get_contents($this->root() . '/README.md');
    $development = substr($readme, strpos($readme, '## Development'));
    foreach (array_keys($config['grumphp']['tasks']) as $task) {
      $this->assertStringContainsString('`' . $task . '`', $development);
    }
    foreach (['no `else`', 'no `exit`', 'no `var_dump` or `kint`', 'composer validate --strict', 'composer audit --no-dev --locked', 'staged files', 'commit-msg hook', 'file filters skip'] as $instruction) {
      $this->assertStringContainsString($instruction, $development);
    }
  }

  /**
   * SCHE-0051/0052: release configuration avoids persisted checkout tokens.
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

}
