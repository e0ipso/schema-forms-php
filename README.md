# Schema Forms

Schema Forms builds forms for PHP frameworks from a JSON Schema that describes the data. You give it the schema and, optionally, a UI schema with per-property display hints. It returns a form definition for the framework.

Drupal is the only supported framework. The generator returns a Drupal Form API render array.

## Installation

A Drupal project that uses this library usually requires it already. To require it yourself:

```
composer require e0ipso/schema-forms
```

The package requires PHP 8.3 or later. CI runs on PHP 8.3, 8.4 and 8.5. The package is tested with Drupal 11. The Drupal generator uses Drupal core classes, and Drupal core is only a dev dependency here, so run the generator inside a Drupal site.

## Usage

Take this JSON Schema for a registration form:

```json
{
  "title": "A registration form",
  "type": "object",
  "required": ["firstName", "lastName"],
  "properties": {
    "firstName": {"type": "string", "title": "First name", "default": "Chuck"},
    "lastName": {"type": "string", "title": "Last name"},
    "age": {"type": "integer", "title": "Age", "description": "(earthian year)"},
    "email": {"type": "string", "format": "email"},
    "bio": {"type": "string", "title": "Bio"},
    "password": {"type": "string", "title": "Password", "minLength": 3},
    "plan": {"type": "string", "enum": ["free", "pro"]},
    "newsletter": {"type": "boolean", "title": "Send me the newsletter"}
  }
}
```

And this UI schema:

```json
{
  "age": {"ui:title": "Age of person"},
  "email": {"ui:placeholder": "you@example.com"},
  "bio": {"ui:widget": "textarea"},
  "password": {"ui:widget": "password", "ui:help": "Hint: Make it strong!"},
  "plan": {
    "ui:widget": "select",
    "ui:enum": {"labels": {"mappings": {"pro": "Pro (paid)"}}}
  }
}
```

Pass both to the generator. Decode the schema to objects and the UI schema to arrays:

```php
use SchemaForms\Drupal\FormGeneratorDrupal;
use Shaper\Util\Context;

$schema_data = json_decode($schema_json);
$ui_schema_data = json_decode($ui_schema_json, TRUE);

$generator = new FormGeneratorDrupal();
$context = new Context(['ui_hints' => $ui_schema_data]);
$form = $generator->transform($schema_data, $context);
```

The public API is `FormGeneratorDrupal` and the `FormGeneratorInterface` it implements, with Shaper's `Context` as input. Semantic versioning covers only these. Every other class in `src/` is marked `@internal` and can change in any release.

`$form` is this render array. The root container also carries `#json_schema` (the decoded schema), `#element_validate` and `#validate`, left out here:

```php
[
  '#type' => 'container',
  '#tree' => TRUE,
  'firstName' => [
    '#title' => 'First name',
    '#type' => 'textfield',
    '#prop_name' => 'firstName',
    '#default_value' => 'Chuck',
    '#prop_parents' => ['firstName'],
    '#disabled' => FALSE,
    '#visible' => TRUE,
    '#required' => TRUE,
  ],
  'lastName' => [
    '#title' => 'Last name',
    '#type' => 'textfield',
    '#prop_name' => 'lastName',
    '#prop_parents' => ['lastName'],
    '#disabled' => FALSE,
    '#visible' => TRUE,
    '#required' => TRUE,
  ],
  'age' => [
    '#title' => 'Age of person',
    '#type' => 'number',
    '#prop_name' => 'age',
    '#description' => '(earthian year)',
    '#step' => 1,
    '#prop_parents' => ['age'],
    '#disabled' => FALSE,
    '#visible' => TRUE,
    '#required' => FALSE,
  ],
  'email' => [
    '#title' => 'Email',
    '#type' => 'email',
    '#prop_name' => 'email',
    '#placeholder' => 'you@example.com',
    '#prop_parents' => ['email'],
    '#disabled' => FALSE,
    '#visible' => TRUE,
    '#required' => FALSE,
  ],
  'bio' => [
    '#title' => 'Bio',
    '#type' => 'textarea',
    '#prop_name' => 'bio',
    '#prop_parents' => ['bio'],
    '#disabled' => FALSE,
    '#visible' => TRUE,
    '#required' => FALSE,
  ],
  'password' => [
    '#title' => 'Password',
    '#type' => 'password',
    '#prop_name' => 'password',
    '#description' => 'Hint: Make it strong!',
    '#prop_parents' => ['password'],
    '#disabled' => FALSE,
    '#visible' => TRUE,
    '#required' => FALSE,
  ],
  'plan' => [
    '#title' => 'Plan',
    '#type' => 'select',
    '#prop_name' => 'plan',
    '#prop_parents' => ['plan'],
    '#options' => ['free' => 'Free', 'pro' => 'Pro (paid)'],
    '#disabled' => FALSE,
    '#visible' => TRUE,
    '#required' => FALSE,
  ],
  'newsletter' => [
    '#title' => 'Send me the newsletter',
    '#type' => 'checkbox',
    '#prop_name' => 'newsletter',
    '#prop_parents' => ['newsletter'],
    '#disabled' => FALSE,
    '#visible' => TRUE,
    '#required' => FALSE,
  ],
]
```

The input schema must be a valid draft-04 JSON Schema whose root `type` is `object`. Anything else makes `transform()` throw a `TypeError`. A property name that starts with `#`, at any depth, makes `transform()` throw an `\InvalidArgumentException` naming the property path, because Drupal would read the name as a render property.

### Context keys

The `Context` accepts three keys, all optional:

- `ui_hints`: the UI schema, as an array keyed by property name.
- `form_state`: the form's `FormStateInterface`. Array properties keep their item list in the form state storage, so pass the real form state when the schema has arrays. Without it the generator uses a fresh `FormState`, and appended items are lost on rebuild.
- `current_input`: previously saved values, keyed like the schema, as decoded objects or associative arrays. Nested objects and arrays are accepted. A value here becomes the element's `#default_value` and wins over the schema's `default`.

### How schema types become elements

| JSON Schema | Form API element |
|---|---|
| `string` | `textfield` |
| `string` with `"format": "email"` | `email` |
| `integer` | `number` with `#step` 1 |
| `number` | `number` |
| `boolean` | `checkbox` |
| any type with `enum` | `radios`, options from the enum |
| `array` whose `items` has an `enum` | `checkboxes`, options from the enum |
| any other `array` whose `items` is one schema object | an open `details` element with one control per item, an "Append an item" button and a remove button per item. Both buttons use AJAX. |
| `object` with `properties` | an open `details` element with one control per sub-property |
| a property with `const` | escaped text via `#plain_text`, with no input; booleans and null use `true`, `false` and `null`, and arrays and objects use JSON |

A type with no element in this list, such as `null`, renders as a disabled item that says the property cannot be edited. It does not disappear from the form. An `array` with no `items`, or with a list of item schemas, renders the same item and its saved value is kept unchanged.

When `type` is a list, the generator uses the first type other than `"null"`. The position of `"null"` does not matter: `["null", "string"]` and `["string", "null"]` both render a textfield. A list containing only `"null"` renders the "cannot be edited" item.

The element title comes from `ui:title`, then the schema `title`, then the property name with `_` and `-` turned into spaces and each word capitalized. Enum option labels come from `ui:enum` mappings, or the enum value formatted the same way.

### UI schema data

The same data shape can be collected with different form controls. The UI schema picks them. Its keys are property names. For an `object` property, nest the sub-property hints under the property's key. An `array` property passes its hints on to every item.

Supported controls:

- `ui:title`: the element label. Defaults to the property's `title`.
- `ui:help`: the element description. Defaults to the property's `description`.
- `ui:placeholder`: placeholder text for the input.
- `ui:widget`: a Drupal Form API element type, such as `select`, `textarea`, `password` or `hidden`. The generator uses the value as `#type` unchanged and does not check it. A name Drupal does not know, such as the react-jsonschema-form widget `updown`, renders nothing. A widget on an `array` or `object` property replaces the multivalue widget or the nested sub-elements, because the widget wins over the schema type. For example, `"tags": {"ui:widget": "textarea"}` on an array property renders one textarea and no per-item controls.
- `ui:enabled`: `0` sets `#disabled` on the element and on every element nested in it, including array items and their sub-properties. A disabled parent wins over a child's own `ui:enabled: 1`.
- `ui:visible`: `0` sets `#visible` to `FALSE`. Drupal core does not read `#visible`, so the element still renders unless your code acts on that key.
- `ui:enum`: controls the labels for select, radio and checkbox options. `ui:enum.labels.mappings` maps each enum value to a label, e.g. `{"uuid1": "Super duper product"}`.

Other keys are ignored.

### Validating submissions

The container can be the whole form, or sit under a key of a larger form. Drupal runs the root container's `#element_validate` callback when the container sits under a key, and its `#validate` handler when the container is the form; either way the schema validation runs once, before the form's own `validateForm()`. When the container is the form, only the schema's properties are validated, so `form_id`, `form_build_id` and the values of elements you add next to the generated ones are left alone. In that placement a button with its own non-empty `#validate` list replaces the form-level handlers, so the schema validation does not run for that button.

When Drupal validates the form, the schema validation:

1. Removes the add and remove button values that array properties leave in the submission.
2. Casts the submitted strings to the schema types. Numeric strings become `int` or `float`, `"0"` and `"1"` become booleans on a `boolean` property, and an empty string becomes `NULL` on a nullable property that cannot hold it.
3. Validates the result against the schema with `justinrainbow/json-schema`. Each error is set on the element it belongs to. Errors that match no element are joined into one error on the container.

### Limitations

- Neither generation nor validation fetches external `$ref` targets. Only references to schemas already registered in memory and the bundled draft-04 meta-schema are allowed; unsupported references produce a container error. Rendering still requires an explicit property `type`; a property that is only an internal `$ref` is not supported.
- Only the root `required` list sets `#required`. A `required` list inside a nested object does not mark its sub-properties.

## Security

Report vulnerabilities privately, as [SECURITY.md](SECURITY.md) describes. Do not file them as public issues.

## Development

`composer test` runs GrumPHP over the tracked files. The tasks in `grumphp.yml` are:

- `git_branch_name`: checks the branch naming convention below.
- `phpunit`: runs PHPUnit when PHP files are present.
- `phpcs`: checks Drupal and DrupalPractice standards with two-space indentation. It checks `php`, `module`, `inc`, `install`, `test`, `profile` and `theme` files, excluding tests and dependency directories.
- `phpmnd`: checks for magic numbers in `php` and `module` files.
- `phplint`: checks PHP syntax in `php`, `module`, `inc`, `install`, `profile` and `theme` files.
- `phpparser`: checks PHP files for parser rules: no `else`, no `exit`, and no `var_dump` or `kint` calls.
- `yamllint` and `jsonlint`: check YAML and JSON files.
- `composer`: runs strict Composer validation, `composer validate --strict`, when `composer.json` or `composer.lock` is present.
- `composer_normalize`: runs `composer normalize --dry-run` when `composer.json` is present.
- `shell`: runs `composer audit --no-dev --locked` when JSON or lock files are present. It audits production dependencies in the lock file.

Tasks with file filters skip when no matching files are present. The pre-commit hook uses staged files for these checks. The remaining task, `git_commit_message`, runs only in the commit-msg hook, not under `composer test`.

Coverage uses class-level `#[CoversClass]` attributes so executed private helpers count toward their class. With Xdebug enabled, run `XDEBUG_MODE=coverage composer test`; with PCOV, run `php -d pcov.enabled=1 -d pcov.directory=src vendor/bin/phpunit`. PHPUnit writes line coverage to `build/logs/clover.xml`. Run `php scripts/check-coverage.php` afterward, or pass a Clover path as its only argument. CI runs this check after `composer test` and fails on coverage below the floor or on a missing or invalid report.

The initial line coverage floor is 45%, defined by `$coverage_floor` in `scripts/check-coverage.php`. To raise it, measure the complete suite with a coverage driver, increase that value to a percentage the suite meets, and update the boundary cases in `tests/coverage-checker.php`. Run those dependency-free checker fixtures with `php tests/coverage-checker.php`. Keep coverage attribution at class level when adding tests.

Kernel tests under `tests/src/Kernel` boot Drupal and submit generated forms through the FormBuilder. They need the `pdo_sqlite` extension: `phpunit.xml` sets `SIMPLETEST_DB=sqlite://localhost/:memory:`, and CI sets the same variable.

### JSON Schema compatibility

Run `composer test` with the normal development installation for JSON Schema 6.x. To run the same GrumPHP checks and complete PHPUnit suite against JSON Schema 5.3.4, use:

```sh
bash scripts/test-json-schema-5.sh
```

Install the normal development dependencies first with `composer install`. The runner needs Bash, Composer, PHP 8.3 or later and the same PHP extensions as the normal suite. Current development tools require JSON Schema 6.x, so the runner installs production dependencies in a disposable directory and overrides their autoloading only inside PHPUnit. It verifies the installed version and the loaded JSON Schema, Shaper and project class paths before running tests and again in PHPUnit's bootstrap. The normal vendor tree, lock file, development requirements and advisory policy are preserved. Composer audits the isolated runtime before tests start.

Temporary files go under `/var/tmp`; set `SCHEMA_COMPAT_TMPDIR` to another existing directory if needed. Successful runs remove them. Failed runs print the retained directory for diagnosis and return the failing command's exit status. Set `SCHEMA_COMPAT_KEEP=1` to retain successful runs too, including coverage reports. Remove the printed directory when finished. Select executables with `PHP_BINARY` and `COMPOSER_BINARY`; container users must make both the checkout and temporary directory visible at the same paths inside the container.

Arguments are passed to GrumPHP, for example `bash scripts/test-json-schema-5.sh --tasks=phpunit`. For scoped diagnostics, `SCHEMA_COMPAT_PHPUNIT_CONFIG` and `SCHEMA_COMPAT_GRUMPHP_CONFIG` accept existing configuration paths; relative PHPUnit inputs resolve beside their original XML. Overrides can limit checks, so omit them for full compatibility verification.

GrumPHP installs these git hooks locally. Commits follow Conventional Commits (`fix(drupal): ...`, with a subject of 50 characters or fewer). Branches are named `<type>/<kebab-case>`, for example `fix/enum-options`. semantic-release tags a release from the commit messages on every push to `master`.
