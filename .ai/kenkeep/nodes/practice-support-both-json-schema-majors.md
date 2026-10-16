---
type: practice
title: Support justinrainbow/json-schema 5.2 and 6.3 side by side
description: >-
  composer allows ^5.2 || ^6.3, and error constraint data changed shape in 6.0.
  Never assume one shape.
tags:
  - dependencies
  - json-schema
  - validation
kk_schema_version: 3
kk_id: practice-support-both-json-schema-majors
kk_derived_from: []
kk_relates_to: []
kk_depends_on: []
kk_confidence: high
---
`composer.json` requires `justinrainbow/json-schema` `^5.2 || ^6.3`, so code that reads validator output must handle both majors. The lock currently installs 6.x.

The known difference is in `$error['constraint']`. In 5.x it is the constraint name as a string. In 6.x it is `['name' => ..., 'params' => [...]]`. `FormValidatorDrupal::constraintSuffix()` accepts both and returns `''` when it finds no name.

When reading any other field of a validator error or using a validator API, check that it exists in both majors.
