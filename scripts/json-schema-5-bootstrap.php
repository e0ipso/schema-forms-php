<?php

/**
 * @file
 * Loads and verifies the isolated production runtime before PHPUnit executes.
 */

declare(strict_types=1);

use Composer\Autoload\ClassLoader;
use Composer\InstalledVersions;

/**
 * Checks installed.php, which Composer writes for InstalledVersions::reload().
 *
 * @param mixed $data
 *   The value the file returned.
 * @param string $path
 *   The file path, for the error message.
 *
 * @phpstan-assert array{root: array{name: string, pretty_version: string, version: string, reference: string|null, type: string, install_path: string, aliases: string[], dev: bool}, versions: array<string, array{pretty_version?: string, version?: string, reference?: string|null, type?: string, install_path?: string, aliases?: string[], dev_requirement: bool, replaced?: string[], provided?: string[]}>} $data
 */
function compat_check_installed(mixed $data, string $path): void {
  if (!is_array($data) || !is_array($data['root'] ?? NULL) || !is_array($data['versions'] ?? NULL)) {
    throw new RuntimeException('Invalid Composer metadata: ' . $path);
  }
}

/**
 * Returns the install path Composer recorded for a package.
 *
 * @param array<string, array{install_path?: string}> $versions
 *   The versions map of installed.php.
 * @param string $package
 *   The package name.
 *
 * @return string
 *   The install path.
 */
function compat_install_path(array $versions, string $package): string {
  $path = $versions[$package]['install_path'] ?? NULL;
  if ($path === NULL) {
    throw new RuntimeException('Missing install path for ' . $package);
  }
  return $path;
}

$settings = json_decode((string) file_get_contents(__DIR__ . '/settings.json'), TRUE, flags: JSON_THROW_ON_ERROR);
if (!is_array($settings) || !is_string($settings['root'] ?? NULL) || !is_string($settings['bootstrap'] ?? NULL)) {
  throw new RuntimeException('Invalid compatibility settings.');
}
$root = $settings['root'];
require $settings['bootstrap'];
// A shared development vendor tree must still test this checkout's source.
$project_loader = new ClassLoader();
$project_loader->addPsr4('SchemaForms\\', $root . '/src');
$project_loader->addPsr4('SchemaForms\\Tests\\', $root . '/tests/src');
$project_loader->register(TRUE);
$installed_path = $root . '/vendor/composer/installed.php';
$installed = require $installed_path;
compat_check_installed($installed, $installed_path);
$runtime_path = __DIR__ . '/runtime/vendor/composer/installed.php';
$runtime = require $runtime_path;
compat_check_installed($runtime, $runtime_path);
if (($runtime['versions']['justinrainbow/json-schema']['version'] ?? NULL) !== '5.3.4.0') {
  throw new RuntimeException('Expected isolated JSON Schema 5.3.4.');
}
$runtime_loader = require __DIR__ . '/runtime/vendor/autoload.php';
if (!$runtime_loader instanceof ClassLoader) {
  throw new RuntimeException('The isolated runtime has no Composer class loader.');
}
$runtime_loader->unregister();
$runtime_loader->register(TRUE);
// A class absent from 5.x must not fall back to the toolchain's 6.x.
spl_autoload_register(static function (string $class) use ($runtime_loader): void {
  if (!str_starts_with($class, 'JsonSchema\\') && !str_starts_with($class, 'Shaper\\')) {
    return;
  }
  $path = $runtime_loader->findFile($class);
  if (!$path) {
    throw new RuntimeException('Class missing from the isolated runtime: ' . $class);
  }
  require $path;
}, TRUE, TRUE);
// Match Composer's reported metadata to the production classes being tested.
$installed['versions'] = array_replace($installed['versions'], $runtime['versions']);
InstalledVersions::reload($installed);
$json_schema_path = compat_install_path($runtime['versions'], 'justinrainbow/json-schema');
foreach ([
  'JsonSchema\\Validator' => $json_schema_path,
  'JsonSchema\\Constraints\\Factory' => $json_schema_path,
  'JsonSchema\\Uri\\UriRetriever' => $json_schema_path,
  'JsonSchema\\SchemaStorage' => $json_schema_path,
  'Shaper\\Validator\\JsonSchemaValidator' => compat_install_path($runtime['versions'], 'e0ipso/shaper'),
  'SchemaForms\\RecursiveTypeCaster' => $root . '/src',
] as $class => $directory) {
  $path = (string) (new ReflectionClass($class))->getFileName();
  if (!str_starts_with($path, realpath($directory) . '/')) {
    throw new RuntimeException(sprintf('Unexpected compatibility class path: %s = %s', $class, $path));
  }
  fwrite(STDOUT, sprintf("%s: %s\n", $class, $path));
}
fwrite(STDOUT, 'Verified JSON Schema ' . InstalledVersions::getPrettyVersion('justinrainbow/json-schema') . PHP_EOL);
