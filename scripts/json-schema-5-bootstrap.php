<?php

/**
 * @file
 * Loads and verifies the isolated production runtime before PHPUnit executes.
 */

declare(strict_types=1);

use Composer\Autoload\ClassLoader;
use Composer\InstalledVersions;

$settings = json_decode(file_get_contents(__DIR__ . '/settings.json'), TRUE, flags: JSON_THROW_ON_ERROR);
$root = $settings['root'];
require $settings['bootstrap'];
// A shared development vendor tree must still test this checkout's source.
$project_loader = new ClassLoader();
$project_loader->addPsr4('SchemaForms\\', $root . '/src');
$project_loader->addPsr4('SchemaForms\\Tests\\', $root . '/tests/src');
$project_loader->register(TRUE);
$installed = require $root . '/vendor/composer/installed.php';
$runtime = require __DIR__ . '/runtime/vendor/composer/installed.php';
$package = $runtime['versions']['justinrainbow/json-schema'];
if ($package['version'] !== '5.3.4.0') {
  throw new RuntimeException('Expected isolated JSON Schema 5.3.4.');
}
$runtime_loader = require __DIR__ . '/runtime/vendor/autoload.php';
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
foreach ([
  'JsonSchema\\Validator' => $package['install_path'],
  'JsonSchema\\Constraints\\Factory' => $package['install_path'],
  'JsonSchema\\Uri\\UriRetriever' => $package['install_path'],
  'JsonSchema\\SchemaStorage' => $package['install_path'],
  'Shaper\\Validator\\JsonSchemaValidator' => $runtime['versions']['e0ipso/shaper']['install_path'],
  'SchemaForms\\RecursiveTypeCaster' => $root . '/src',
] as $class => $directory) {
  $path = (new ReflectionClass($class))->getFileName();
  if (!str_starts_with($path, realpath($directory) . '/')) {
    throw new RuntimeException(sprintf('Unexpected compatibility class path: %s = %s', $class, $path));
  }
  fwrite(STDOUT, sprintf("%s: %s\n", $class, $path));
}
fwrite(STDOUT, 'Verified JSON Schema ' . InstalledVersions::getPrettyVersion('justinrainbow/json-schema') . PHP_EOL);
