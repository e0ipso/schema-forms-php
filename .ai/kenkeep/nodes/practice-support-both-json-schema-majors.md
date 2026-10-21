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
kk_derived_from:
  - src/SchemaReferenceResolver.php
kk_relates_to:
  - practice-restrict-schema-reference-retrieval
kk_depends_on: []
kk_confidence: high
---
`composer.json` requires `justinrainbow/json-schema` `^5.2 || ^6.3`, so code that reads validator output must handle both majors. The current development dependency graph resolves 6.x; verifying that environment alone does not establish 5.x compatibility.

The known difference is in `$error['constraint']`. In 5.x it is the constraint name as a string. In 6.x it is `['name' => ..., 'params' => [...]]`. `FormValidatorDrupal::constraintSuffix()` accepts both and returns `''` when it finds no name.

When reading any other field of a validator error or using a validator API, check that it exists in both majors.

`SchemaReferenceResolver::createFactory()` registers both `internal://schema-with-refs/` and its public `SCHEMA_URI`, `internal:/schema-with-refs`. The double-slash alias keeps local reference expansion in memory on older 5.x resolvers, which otherwise treat the single-slash URI as a local file base. Preserve both registrations when changing the restricted resolver. Use an isolated compatible runtime to verify 5.x when the development graph cannot resolve it.

<!-- kk:related:start -->
# Related

- Related: [practice-restrict-schema-reference-retrieval](practice-restrict-schema-reference-retrieval.md)
<!-- kk:related:end -->

<!-- kk:citations:start -->
# Citations

[1] [src/SchemaReferenceResolver.php](../../../src/SchemaReferenceResolver.php)
<!-- kk:citations:end -->
