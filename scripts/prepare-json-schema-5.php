<?php

/**
 * @file
 * Prepares disposable runtime dependencies and compatibility configurations.
 */

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
$json_schema_version = '5.3.4';
$expected_arguments = 4;
if ($argc !== $expected_arguments) {
  throw new RuntimeException('Usage: php scripts/prepare-json-schema-5.php <work-dir> <phpunit.xml> <grumphp.yml>');
}
[, $work, $phpunit_path, $grumphp_path] = $argv;

/**
 * Writes a file and fails loudly when the write fails.
 *
 * @param string $path
 *   The file path.
 * @param string $contents
 *   The contents.
 */
function prepare_write(string $path, string $contents): void {
  if (file_put_contents($path, $contents) === FALSE) {
    throw new RuntimeException('Cannot write ' . $path);
  }
}

$manifest_json = file_get_contents($root . '/composer.json');
$manifest = json_decode((string) $manifest_json, TRUE, flags: JSON_THROW_ON_ERROR);
if (!is_array($manifest) || !is_array($manifest['require'] ?? NULL)) {
  throw new RuntimeException('Cannot read the requirements in composer.json.');
}
// Copy production requirements and dependency policy, but never install tools.
$runtime = array_intersect_key($manifest, array_flip([
  'require', 'conflict', 'repositories', 'minimum-stability', 'prefer-stable', 'config',
]));
$require = $manifest['require'];
$require['justinrainbow/json-schema'] = $json_schema_version;
$runtime['require'] = $require;
// Paths and plugins belong to the contributor's normal installation.
if (is_array($runtime['config'] ?? NULL)) {
  unset($runtime['config']['vendor-dir'], $runtime['config']['bin-dir'], $runtime['config']['allow-plugins']);
}
if (!mkdir($work . '/runtime')) {
  throw new RuntimeException('Cannot create ' . $work . '/runtime');
}
prepare_write($work . '/runtime/composer.json', json_encode($runtime, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

$phpunit_path = realpath($phpunit_path);
$grumphp_path = realpath($grumphp_path);
if (!$phpunit_path || !$grumphp_path) {
  throw new RuntimeException('Cannot read PHPUnit or GrumPHP configuration.');
}
$xml = new DOMDocument();
if (!$xml->load($phpunit_path, LIBXML_NONET) || !$xml->documentElement) {
  throw new RuntimeException('Cannot parse ' . $phpunit_path);
}
$phpunit = $xml->documentElement;
$xpath = new DOMXPath($xml);
$bootstrap = $phpunit->getAttribute('bootstrap');
$settings = [
  'root' => $root,
  'bootstrap' => str_starts_with($bootstrap, '/') ? $bootstrap : dirname($phpunit_path) . '/' . $bootstrap,
];
prepare_write($work . '/settings.json', json_encode($settings, JSON_THROW_ON_ERROR));
if (!copy(__DIR__ . '/json-schema-5-bootstrap.php', $work . '/bootstrap.php')) {
  throw new RuntimeException('Cannot copy the compatibility bootstrap.');
}
$phpunit->setAttribute('bootstrap', $work . '/bootstrap.php');
// Keep relative input paths anchored to the original XML, including fixtures.
foreach ($xpath->query('//testsuite/directory | //testsuite/file | //testsuite/exclude | //source//directory | //source//file') ?: [] as $node) {
  if ($node instanceof DOMElement && !str_starts_with($node->textContent, '/')) {
    $node->nodeValue = dirname($phpunit_path) . '/' . $node->textContent;
  }
}
// Reports and caches stay disposable; KEEP=1 preserves them for inspection.
foreach ($xpath->query('//@outputFile | //@outputDirectory | //@cacheDirectory | //@cacheResultFile') ?: [] as $attribute) {
  if ($attribute instanceof DOMAttr) {
    $attribute->value = $work . '/' . basename($attribute->value);
  }
}
$phpunit->setAttribute('cacheDirectory', $work . '/phpunit-cache');
if ($xml->save($work . '/phpunit.xml') === FALSE) {
  throw new RuntimeException('Cannot write ' . $work . '/phpunit.xml');
}
$config = Yaml::parseFile($grumphp_path);
$grumphp = is_array($config) ? $config['grumphp'] ?? NULL : NULL;
$tasks = is_array($grumphp) ? $grumphp['tasks'] ?? NULL : NULL;
if (!is_array($config) || !is_array($grumphp) || !is_array($tasks)) {
  throw new RuntimeException('Cannot find the GrumPHP tasks in ' . $grumphp_path);
}
$phpunit_task = is_array($tasks['phpunit'] ?? NULL) ? $tasks['phpunit'] : [];
$phpunit_task['config_file'] = $work . '/phpunit.xml';
$tasks['phpunit'] = $phpunit_task;
$grumphp['tasks'] = $tasks;
$config['grumphp'] = $grumphp;
prepare_write($work . '/grumphp.yml', Yaml::dump($config, PHP_INT_MAX));
