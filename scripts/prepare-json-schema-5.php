<?php

/**
 * @file
 * Prepares disposable runtime dependencies and compatibility configurations.
 */

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
[, $work, $phpunit_path, $grumphp_path] = $argv;
$manifest = json_decode(file_get_contents($root . '/composer.json'), TRUE, flags: JSON_THROW_ON_ERROR);
// Copy production requirements and dependency policy, but never install tools.
$runtime = array_intersect_key($manifest, array_flip([
  'require', 'conflict', 'repositories', 'minimum-stability', 'prefer-stable', 'config',
]));
$runtime['require']['justinrainbow/json-schema'] = '5.3.4';
// Paths and plugins belong to the contributor's normal installation.
unset($runtime['config']['vendor-dir'], $runtime['config']['bin-dir'], $runtime['config']['allow-plugins']);
mkdir($work . '/runtime');
file_put_contents($work . '/runtime/composer.json', json_encode($runtime, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

$phpunit_path = realpath($phpunit_path);
$grumphp_path = realpath($grumphp_path);
if (!$phpunit_path || !$grumphp_path) {
  throw new RuntimeException('Cannot read PHPUnit or GrumPHP configuration.');
}
$xml = new DOMDocument();
$xml->load($phpunit_path, LIBXML_NONET);
$xpath = new DOMXPath($xml);
$settings = [
  'root' => $root,
  'bootstrap' => dirname($phpunit_path) . '/' . $xml->documentElement->getAttribute('bootstrap'),
];
if (str_starts_with($xml->documentElement->getAttribute('bootstrap'), '/')) {
  $settings['bootstrap'] = $xml->documentElement->getAttribute('bootstrap');
}
file_put_contents($work . '/settings.json', json_encode($settings, JSON_THROW_ON_ERROR));
copy(__DIR__ . '/json-schema-5-bootstrap.php', $work . '/bootstrap.php');
$xml->documentElement->setAttribute('bootstrap', $work . '/bootstrap.php');
// Keep relative input paths anchored to the original XML, including fixtures.
foreach ($xpath->query('//testsuite/directory | //testsuite/file | //testsuite/exclude | //source//directory | //source//file') as $node) {
  if (!str_starts_with($node->textContent, '/')) {
    $node->nodeValue = dirname($phpunit_path) . '/' . $node->textContent;
  }
}
// Reports and caches stay disposable; KEEP=1 preserves them for inspection.
foreach ($xpath->query('//@outputFile | //@outputDirectory | //@cacheDirectory | //@cacheResultFile') as $attribute) {
  $attribute->value = $work . '/' . basename($attribute->value);
}
$xml->documentElement->setAttribute('cacheDirectory', $work . '/phpunit-cache');
$xml->save($work . '/phpunit.xml');
$config = Yaml::parseFile($grumphp_path);
$config['grumphp']['tasks']['phpunit']['config_file'] = $work . '/phpunit.xml';
file_put_contents($work . '/grumphp.yml', Yaml::dump($config, PHP_INT_MAX));
