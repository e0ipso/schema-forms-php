<?php

/**
 * @file
 * Enforces the project line coverage floor using PHPUnit's Clover report.
 */

declare(strict_types=1);

$coverage_floor = 45;
$percentage_scale = 100;
$max_arguments = 2;

if ($argc > $max_arguments) {
  throw new RuntimeException('Usage: php scripts/check-coverage.php [clover.xml]');
}

$path = $argv[1] ?? __DIR__ . '/../build/logs/clover.xml';
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
if (count($metrics) !== 1) {
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
// Compare the unrounded value: 44.999% must fail a 45% floor.
if ($percentage < $coverage_floor) {
  throw new RuntimeException($message);
}
fwrite(STDOUT, $message . PHP_EOL);
