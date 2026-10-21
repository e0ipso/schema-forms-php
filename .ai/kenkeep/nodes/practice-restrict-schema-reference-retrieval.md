---
type: practice
title: Keep schema references inside registered documents
description: >-
  Use one restricted resolver for rendering, submission and blank refinement;
  external file and HTTP targets must never be fetched.
tags:
  - json-schema
  - validation
  - security
  - gotcha
kk_schema_version: 3
kk_id: practice-restrict-schema-reference-retrieval
kk_derived_from: []
kk_relates_to:
  - map-form-generator-drupal
  - map-submission-validation-pipeline
  - practice-support-both-json-schema-majors
kk_depends_on: []
kk_confidence: high
---
`SchemaReferenceResolver::createFactory()` supplies the shared restricted retriever to generation, submission validation and `RecursiveTypeCaster` optional-blank checks. Allow references to schemas registered in memory and the bundled `meta-schema-draft4.json` only. The draft-04 HTTP and HTTPS metadata identifiers map to that bundled file; they do not authorize network retrieval. Never delegate an unknown URI to a generic retriever, including `file:` and `internal:` URIs.

Unsupported external references produce an unsupported-reference container error, with no file read or HTTP request. `checkReferences()` checks schema branches before optional blanks can disappear during refinement. Traverse schema keywords rather than literal `default`, `enum` or `const` values. Otherwise an unused branch or omitted blank could conceal an unsupported reference.

Preserve both internal storage aliases for supported validator majors, as described in the related compatibility note. Rendering still needs an explicit property `type`; a property containing only an internal `$ref` is not supported. `tests/src/SchemaReferenceResolverTest.php` uses `tests/src/Fixture/ReferenceSentinel.php` to verify that prohibited targets are never retrieved, including generation, submission and optional-blank paths. README documents the same reference policy.

<!-- kk:related:start -->
# Related

- Related: [map-form-generator-drupal](map-form-generator-drupal.md)
- Related: [map-submission-validation-pipeline](map-submission-validation-pipeline.md)
- Related: [practice-support-both-json-schema-majors](practice-support-both-json-schema-majors.md)
<!-- kk:related:end -->
