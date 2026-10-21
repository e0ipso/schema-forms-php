<?php

/**
 * @file
 * Dependency-free CLI fixtures for the coverage floor checker.
 */

declare(strict_types=1);

$metrics = static fn (string $total, string $covered): string => sprintf('<metrics statements="%s" coveredstatements="%s"/>', $total, $covered);
$clover = static fn (string $body): string => '<coverage><project>' . $body . '</project></coverage>';
$fixtures = [
  'below floor' => [$clover($metrics('100', '44')), FALSE, '44.00%'],
  'at floor' => [$clover($metrics('100', '45')), TRUE, '45.00%'],
  'above floor' => [$clover($metrics('100', '80')), TRUE, '80.00%'],
  'unrounded below floor' => [$clover($metrics('100000', '44999')), FALSE, '45.00%'],
  'zero covered lines' => [$clover($metrics('100', '0')), FALSE, '0.00%'],
  'project totals only' => [$clover('<file>' . $metrics('100', '100') . '</file>' . $metrics('100', '44')), FALSE, '44.00%'],
  'line counts only' => [$clover('<metrics statements="100" coveredstatements="44" elements="100" coveredelements="100"/>'), FALSE, '44.00%'],
  'missing report' => [NULL, FALSE, 'Cannot read Clover report'],
  'malformed report' => ['<coverage>', FALSE, 'Invalid Clover XML'],
  'missing metrics' => [$clover(''), FALSE, 'Expected one project-level'],
  'duplicate metrics' => [$clover($metrics('100', '100') . $metrics('100', '100')), FALSE, 'Expected one project-level'],
  'empty counts' => [$clover($metrics('0', '0')), FALSE, 'empty or inconsistent'],
  'missing counts' => [$clover('<metrics/>'), FALSE, 'non-negative integers'],
  'negative counts' => [$clover($metrics('100', '-1')), FALSE, 'non-negative integers'],
  'decimal counts' => [$clover($metrics('100', '45.5')), FALSE, 'non-negative integers'],
  'nonnumeric counts' => [$clover($metrics('NaN', '45')), FALSE, 'non-negative integers'],
  'excess covered lines' => [$clover($metrics('100', '101')), FALSE, 'empty or inconsistent'],
];

$directory = sys_get_temp_dir() . '/schema-coverage-' . bin2hex(random_bytes(8));
if (!mkdir($directory)) {
  throw new RuntimeException('Cannot create coverage fixture directory.');
}
$path = $directory . '/clover.xml';
$assertions = 0;
try {
  foreach ($fixtures as $name => [$xml, $passes, $message]) {
    if ($xml !== NULL) {
      file_put_contents($path, $xml);
    }
    $process = proc_open(
      [PHP_BINARY, __DIR__ . '/../scripts/check-coverage.php', $path],
      [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
      $pipes,
    );
    if ($process === FALSE) {
      throw new RuntimeException('Cannot start the coverage checker.');
    }
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($process);
    if (is_file($path)) {
      unlink($path);
    }
    if (($code === 0) !== $passes) {
      throw new RuntimeException(sprintf('%s: unexpected exit %d. %s%s', $name, $code, $output, $error));
    }
    $assertions++;
    if (!str_contains($output . $error, $message)) {
      throw new RuntimeException(sprintf('%s: missing diagnostic %s. %s%s', $name, $message, $output, $error));
    }
    $assertions++;
  }
}
finally {
  if (is_file($path)) {
    unlink($path);
  }
  rmdir($directory);
}
if (file_exists($directory)) {
  throw new RuntimeException('Coverage fixture cleanup failed.');
}
$assertions++;
printf("OK (%d fixtures, %d assertions); cleanup confirmed.\n", count($fixtures), $assertions);
