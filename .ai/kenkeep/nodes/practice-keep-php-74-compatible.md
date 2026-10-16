---
type: practice
title: Keep src/ runnable on PHP 7.4
description: >-
  composer.json allows php >=7.4 on purpose, but CI only runs 8.3 and 8.4, so
  nothing catches PHP 8 syntax.
tags:
  - php
  - compatibility
  - ci
kk_schema_version: 3
kk_id: practice-keep-php-74-compatible
kk_derived_from: []
kk_relates_to: []
kk_depends_on: []
kk_confidence: medium
---
`composer.json` requires `php >=7.4`, and two `feat: allow using PHP 74 as well` commits plus `fix: make type caster compatible with php7` show that this is deliberate. Do not use PHP 8-only syntax in `src/`: no `match`, nullsafe `?->`, named arguments, union or `mixed` type declarations, constructor promotion, `readonly` or enums. `mixed` is fine in docblocks.

PHP 8.1 functions come from `symfony/polyfill-php81`, a runtime dependency. That is how `array_is_list()` works in `UserInputCleaner` and `RecursiveTypeCaster`. A function from another PHP 8 release needs its own polyfill.

The CI matrix in `.github/workflows/php.yml` only runs PHP 8.3 and 8.4, and `config.platform.php` is pinned to 8.3.0. A 7.4 break passes CI, so check new syntax by hand.
