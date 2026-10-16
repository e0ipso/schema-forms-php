---
type: practice
title: 'Run composer test: GrumPHP runs tests, Drupal phpcs and code rules'
description: >-
  composer test runs PHPUnit, Drupal/DrupalPractice phpcs, phpmnd and a parser
  that bans else, exit and var_dump.
tags:
  - tooling
  - testing
  - coding-standards
kk_schema_version: 3
kk_id: practice-run-composer-test-grumphp
kk_derived_from: []
kk_relates_to: []
kk_depends_on: []
kk_confidence: high
---
`composer test` runs `vendor/bin/grumphp.phar run` with `grumphp.yml`, and CI runs the same command. The tasks are PHPUnit, PHP_CodeSniffer with the `Drupal` and `DrupalPractice` standards (2-space indent, `tests/` excluded), phplint, phpmnd, composer validate and normalize, yamllint, jsonlint and a security check of `composer.lock`.

The `phpparser` task bans `else`, `exit` and calls to `var_dump` or `kint`. Write early returns and guard clauses. `src/` has no `else`.

phpmnd flags magic numbers. The code names them instead, as `$two = 2;` in `UserInputCleaner::cleanRemoveButton()` does.

PHPUnit 10 tests live in `tests/src`, autoloaded as `SchemaForms\Tests\`. Regression cases in the data providers carry a comment that names the defect they guard.

`phpunit.xml` configures coverage, so PHPUnit 10 exits 1 when no coverage driver is installed, even with every test passing. CI installs xdebug. Locally, install xdebug or pcov, or run `vendor/bin/phpunit --no-coverage`.
