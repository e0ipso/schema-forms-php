<?php

/**
 * @file
 * PHPUnit bootstrap that makes Drupal's kernel test classes autoloadable.
 *
 * Drupal core lives in vendor/drupal/core with no web root, so its own
 * tests/bootstrap.php cannot run: it requires an autoload.php next to core.
 * This file registers the core test namespaces on the project autoloader
 * instead.
 */

declare(strict_types=1);

use Composer\InstalledVersions;

$loader = require dirname(__DIR__) . '/vendor/autoload.php';

$core_tests = InstalledVersions::getInstallPath('drupal/core') . '/tests';
foreach (['Drupal\\Tests', 'Drupal\\KernelTests', 'Drupal\\TestTools'] as $prefix) {
  $loader->add($prefix, $core_tests);
}
