<?php

/**
 * @file
 * Enforces the project line coverage floor using PHPUnit's Clover report.
 */

declare(strict_types=1);

// The suite measures about 99.6%; the floor leaves a small margin.
$coverage_floor = 98;
$percentage_scale = 100;
$max_arguments = 2;

// $_SERVER['argv'] has a type PHPStan can check; the $argv global may not.
$arguments = is_array($_SERVER['argv'] ?? NULL) ? $_SERVER['argv'] : [];
$path = $arguments[1] ?? __DIR__ . '/../build/logs/clover.xml';
if (count($arguments) > $max_arguments || !is_string($path)) {
  throw new RuntimeException('Usage: php scripts/check-coverage.php [clover.xml]');
}
if (!is_file($path) || !is_readable($path)) {
  throw new RuntimeException(sprintf('Cannot read Clover report: %s', $path));
}

$previous_errors = libxml_use_internal_errors(TRUE);
try {
  $report = simplexml_load_file($path, SimpleXMLElement::class, LIBXML_NONET);
}
finally {
  libxml_clear_errors();
  libxml_use_internal_errors($previous_errors);
}
if ($report === FALSE) {
  throw new RuntimeException('Invalid Clover XML.');
}

$metrics = $report->xpath('/coverage/project/metrics');
if (!is_array($metrics) || count($metrics) !== 1) {
  throw new RuntimeException('Expected one project-level Clover metrics element.');
}
$statements = (string) $metrics[0]['statements'];
$covered = (string) $metrics[0]['coveredstatements'];
if (!ctype_digit($statements) || !ctype_digit($covered)) {
  throw new RuntimeException('Clover statement counts must be non-negative integers.');
}
$statements = (float) $statements;
$covered = (float) $covered;
if (!is_finite($statements) || $statements <= 0 || $covered > $statements) {
  throw new RuntimeException('Clover statement counts are empty or inconsistent.');
}

$percentage = $percentage_scale * $covered / $statements;
$message = sprintf('Line coverage: %.2f%% (%.0f/%.0f); required: %d%%.', $percentage, $covered, $statements, $coverage_floor);
// Compare the unrounded value: 97.999% must fail a 98% floor.
if ($percentage < $coverage_floor) {
  throw new RuntimeException($message);
}
fwrite(STDOUT, $message . PHP_EOL);
