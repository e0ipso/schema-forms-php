#!/usr/bin/env bash
# Run the existing checks with an isolated JSON Schema 5 production runtime.
set -euo pipefail

root=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)
cd -- "$root"
php_bin=${PHP_BINARY:-php}
composer_bin=${COMPOSER_BINARY:-composer}
work=$(mktemp -d "${SCHEMA_COMPAT_TMPDIR:-/var/tmp}/schema-forms-json5.XXXXXXXX")
cleanup() {
  code=$?
  if [[ "$code" != 0 || ${SCHEMA_COMPAT_KEEP:-0} == 1 ]]; then
    printf 'Compatibility files retained at %s (exit %s)\n' "$work" "$code" >&2
  else
    rm -rf -- "$work"
  fi
}
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
printf 'Compatibility workspace: %s\n' "$work"

"$php_bin" "$root/scripts/prepare-json-schema-5.php" "$work" \
  "${SCHEMA_COMPAT_PHPUNIT_CONFIG:-$root/phpunit.xml}" \
  "${SCHEMA_COMPAT_GRUMPHP_CONFIG:-$root/grumphp.yml}"
(
  cd -- "$work/runtime"
  # Do not let Composer environment overrides redirect this disposable install.
  unset COMPOSER COMPOSER_VENDOR_DIR
  "$composer_bin" install --no-dev --no-interaction --no-progress --no-plugins --no-scripts
  "$composer_bin" audit --no-dev --locked --no-interaction
)
"$php_bin" "$work/bootstrap.php"
"$php_bin" "$root/vendor/bin/grumphp.phar" run "$@" --no-interaction --config="$work/grumphp.yml"
