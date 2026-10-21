---
type: practice
title: 'Run composer test: GrumPHP runs tests, Drupal phpcs and code rules'
description: >-
  composer test runs GrumPHP with PHPUnit 11.5 and strict Composer validation;
  CI separately enforces the coverage floor.
tags:
  - tooling
  - testing
  - coding-standards
kk_schema_version: 3
kk_id: practice-run-composer-test-grumphp
kk_derived_from:
  - README.md
kk_relates_to:
  - map-submission-validation-pipeline
  - practice-conventional-commits-and-branches
kk_depends_on: []
kk_confidence: high
---
`composer test` runs `php vendor/bin/grumphp.phar run` with `grumphp.yml`. GrumPHP runs PHPUnit, Drupal/DrupalPractice PHP_CodeSniffer with 2-space indentation and `tests/` excluded, phplint, phpmnd, strict Composer validation, composer normalize, YAML/JSON lint, and `composer audit --no-dev --locked`. Its parser bans `else`, `exit`, `var_dump` and `kint`; use guard clauses and named numeric values. Composer warnings fail the `composer` task because `strict: true` (`composer.json:69`; `grumphp.yml:44`). `phpparser` is the configured static rule set; phpstan is installed as a dependency but has no GrumPHP task or project configuration. GrumPHP's `process_timeout` is 300 seconds, so a hung task fails instead of blocking. `testDevelopmentDocumentsEveryTask()` in `tests/src/RepositoryConfigurationTest.php` requires every `grumphp.yml` task to be named in the README `## Development` section, so add or rename a task there too.

The project requires PHPUnit `^11.5`; tests are autoloaded under `SchemaForms\Tests\` from `tests/src`. Keep coverage attribution at class level with `#[CoversClass]`, so executed private helpers count toward their class. Kernel tests submit generated forms through Drupal FormBuilder and need `pdo_sqlite`; `phpunit.xml` supplies `SIMPLETEST_DB=sqlite://localhost/:memory:` (`composer.json:37`; `tests/src/RecursiveTypeCasterTest.php:15`; `README.md:244`). When a test asserts on a PHP child process's error text, search stdout and stderr together: where an uncaught exception message lands depends on the PHP `display_errors` and `log_errors` settings.

For coverage with Xdebug, run `XDEBUG_MODE=coverage composer test`; with PCOV, run `php -d pcov.enabled=1 -d pcov.directory=src vendor/bin/phpunit`. PHPUnit writes `build/logs/clover.xml` and `build/logs/junit.xml`; `/build` is git-ignored. A local `php vendor/bin/phpunit --no-coverage` run exercises tests without a driver but does not verify the coverage gate. CI runs the Composer suite with Xdebug, then `php tests/coverage-checker.php` and `php scripts/check-coverage.php` (`phpunit.xml:8`; `.github/workflows/php.yml:51`).

The initial line coverage floor is 45%, defined by `$coverage_floor` in `scripts/check-coverage.php`. The checker reads project statement totals, compares the unrounded ratio, and rejects missing, malformed, empty or inconsistent reports; it also accepts one explicit Clover path. To raise the floor, measure the complete suite with a driver, increase the constant to an attainable floor, and update boundary fixtures in `tests/coverage-checker.php`. Run those dependency-free fixtures with `php tests/coverage-checker.php` (`scripts/check-coverage.php:10`; `README.md:242`). For full verification run `composer test -- --no-interaction` and `bash scripts/test-json-schema-5.sh` with default configuration; scoped task filters or configuration overrides are diagnostics only.

Task filters skip checks when no matching files are present. The pre-commit hook checks staged files, while `composer test` runs over tracked files. `git_commit_message` runs only in the commit-msg hook. Keep this trigger distinction in README instructions; a green Composer run does not validate a proposed commit message.

<!-- kk:related:start -->
# Related

- Related: [map-submission-validation-pipeline](map-submission-validation-pipeline.md)
- Related: [practice-conventional-commits-and-branches](practice-conventional-commits-and-branches.md)
<!-- kk:related:end -->

<!-- kk:citations:start -->
# Citations

[1] [README.md](../../../README.md)
<!-- kk:citations:end -->
