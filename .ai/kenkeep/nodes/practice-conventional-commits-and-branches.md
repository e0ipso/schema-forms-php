---
type: practice
title: Use Conventional Commits and type/kebab branch names
description: >-
  GrumPHP hooks enforce commit type/scope and a 50-char subject.
  semantic-release tags master from commits.
tags:
  - git
  - release
  - conventions
kk_schema_version: 3
kk_id: practice-conventional-commits-and-branches
kk_derived_from:
  - grumphp.yml
kk_relates_to:
  - practice-run-composer-test-grumphp
kk_depends_on: []
kk_confidence: high
---
GrumPHP installs local git hooks (`hooks_preset: local`) that check commits. The type is one of `build, ci, chore, docs, feat, fix, perf, refactor, revert, style, test`. The configured scopes are `schema, form, drupal, api, deps, deps-dev`; the scope is optional. The subject is at most 50 characters, with no trailing period or punctuation. Branch names must match `<type>/<kebab-case>`, apart from `master` and `main`.

Releases are automatic. `.github/workflows/release.yml` runs semantic-release with the `conventionalcommits` preset on every push to `master`. `fix:` gives a patch and `feat:` a minor, so the commit type decides the version.

In `grumphp.yml`, `git_commit_message.type_scope_conventions` must use the named `types` and `scopes` keys. A positional mapping can pass configuration loading while silently bypassing type/scope validation. The task runs in the commit-msg hook; `composer test` does not exercise it. Keep README instructions aligned with the installed GrumPHP task.

CI and release checkouts set `persist-credentials: false`. The semantic-release action resolves plugins from its own installation, so a plugin named by `.releaserc` that the action does not bundle must be pinned in `extra_plugins` in `.github/workflows/release.yml`. Dependabot does not update these embedded plugin pins; review and update them explicitly.

<!-- kk:related:start -->
# Related

- Related: [practice-run-composer-test-grumphp](practice-run-composer-test-grumphp.md)
<!-- kk:related:end -->

<!-- kk:citations:start -->
# Citations

[1] [grumphp.yml](../../../grumphp.yml)
<!-- kk:citations:end -->
