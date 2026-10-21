---
type: practice
title: 'Run composer test: GrumPHP runs tests, Drupal phpcs and code rules'
description: >-
  composer test runs GrumPHP with PHPUnit 11.5 and strict Composer validation; CI separately enforces the coverage floor.
tags:
  - tooling
  - testing
  - coding-standards
kk_schema_version: 3
kk_id: practice-run-composer-test-grumphp
kk_derived_from: []
kk_relates_to:
  - map-submission-validation-pipeline
kk_depends_on: []
kk_confidence: high
---
`composer test` runs `php vendor/bin/grumphp.phar run` with `grumphp.yml`. GrumPHP runs PHPUnit, Drupal/DrupalPractice PHP_CodeSniffer with 2-space indentation and `tests/` excluded, phplint, phpmnd, strict Composer validation, composer normalize, YAML/JSON lint, and `composer audit --no-dev --locked`. Its parser bans `else`, `exit`, `var_dump` and `kint`; use guard clauses and named numeric values. Composer warnings fail the `composer` task because `strict: true` (`composer.json:69`; `grumphp.yml:44`).

The project requires PHPUnit `^11.5`; tests are autoloaded under `SchemaForms\Tests\` from `tests/src`. Keep coverage attribution at class level with `#[CoversClass]`, so executed private helpers count toward their class. Kernel tests submit generated forms through Drupal FormBuilder and need `pdo_sqlite`; `phpunit.xml` supplies `SIMPLETEST_DB=sqlite://localhost/:memory:` (`composer.json:37`; `tests/src/RecursiveTypeCasterTest.php:15`; `README.md:227`).

For coverage with Xdebug, run `XDEBUG_MODE=coverage composer test`; with PCOV, run `php -d pcov.enabled=1 -d pcov.directory=src vendor/bin/phpunit`. PHPUnit writes `build/logs/clover.xml`. A local `php vendor/bin/phpunit --no-coverage` run exercises tests without a driver but does not verify the coverage gate. CI runs the Composer suite with Xdebug, then `php tests/coverage-checker.php` and `php scripts/check-coverage.php` (`phpunit.xml:8`; `.github/workflows/php.yml:51`).

The initial line coverage floor is 45%, defined by `$coverage_floor` in `scripts/check-coverage.php`. The checker reads project statement totals, compares the unrounded ratio, and rejects missing, malformed, empty or inconsistent reports; it also accepts one explicit Clover path. To raise the floor, measure the complete suite with a driver, increase the constant to an attainable floor, and update boundary fixtures in `tests/coverage-checker.php`. Run those dependency-free fixtures with `php tests/coverage-checker.php` (`scripts/check-coverage.php:10`; `README.md:229`).
