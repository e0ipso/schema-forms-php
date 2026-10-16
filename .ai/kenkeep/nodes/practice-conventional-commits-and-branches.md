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
kk_derived_from: []
kk_relates_to: []
kk_depends_on: []
kk_confidence: high
---
GrumPHP installs local git hooks (`hooks_preset: local`) that check commits. The type is one of `build, ci, chore, docs, feat, fix, perf, refactor, revert, style, test`. The configured scopes are `schema, form, drupal, api`. The subject is at most 50 characters, with no trailing period or punctuation. Branch names must match `<type>/<kebab-case>`, apart from `master` and `main`.

Releases are automatic. `.github/workflows/release.yml` runs semantic-release with the `conventionalcommits` preset on every push to `master`. `fix:` gives a patch and `feat:` a minor, so the commit type decides the version.
