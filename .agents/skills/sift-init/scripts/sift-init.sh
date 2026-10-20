#!/usr/bin/env bash
# sift-init: materialise a sift tree at an already-resolved root. Idempotent.
#
# Usage:
#   sift-init.sh --root PATH --prefix ABCD [--milestone NAME]
#                [--worktree-link PATH]... [--worktree-command CMD]...
#   sift-init.sh --suggest-prefix --root PATH
#   sift-init.sh --suggest-worktree-links --root PATH
#
# Options:
#   --root PATH               root already resolved by sift-gate.sh
#   --prefix ABCD             immutable ticket prefix
#   --milestone NAME          first milestone (default: backlog)
#   --worktree-link PATH      repeatable; symlink PATH from this root into each
#                             new drain worktree
#   --worktree-command CMD    repeatable; run CMD in each new drain worktree
#   --suggest-prefix          print a derived prefix and write nothing
#   --suggest-worktree-links  print the gitignored paths a new worktree would
#                             lack, one per line, and write nothing
#   -h, --help                print this header
#
# Prefix suggestion strips non-alphanumerics from the root basename, uppercases
# it, and prints the first four characters. No usable suggestion exits 1 silently.
#
# Link suggestion asks git which untracked paths it ignores at the root. A fully
# ignored directory is one line, without its trailing slash. Output is sorted
# with `LC_ALL=C sort -u`. It never lists .ai, .ai/sift or anything under it, or
# a name git can only print quoted. Outside a git work tree it prints nothing.
#
# Any --worktree-link or --worktree-command also writes the executable bash
# script .ai/sift/config/worktree-setup. prepare-worktree.sh runs it in each new
# drain worktree, with that worktree as cwd and SIFT_ROOT set to this root.
# Link steps come first, then command steps, each in the order given. A link
# step symlinks $SIFT_ROOT/PATH to PATH and does nothing when the source is
# missing or PATH already exists. A command step runs CMD verbatim. An existing
# hook is kept as it is, whatever options this run was given.
# A link must be relative, with no empty, `.` or `..` component, and must not
# be .ai, .ai/sift or under it. A command must be one non-blank line that
# `bash -n` parses with no error or warning. Either violation exits 2 before
# anything is written.
#
# Writes are create-if-absent. A repeat run repairs missing scaffolding without
# replacing repository content. Shipped README/schema drift is reported with
# explicit refresh commands; installed schemas no longer shipped are `orphan`.
#
# Output lists created, kept, stale, and orphan paths plus the selected prefix.
# Stale entries include explicit asset refresh commands. Orphan entries include
# a manual removal command. Neither condition rewrites an existing file.
# Deleting a possible repository-owned schema is the repair this script must
# never make on its own.
#
# A final sift-gate check reports whether the resulting tree is READY.
#
# Reports are written to stdout; diagnostics are written to stderr.
# Exit codes:
#   0  created, repaired, or reported drift
#   1  no usable prefix suggestion
#   2  usage or environment error

set -u

root=''; prefix=''; milestone='backlog'; suggest=0; suggest_links=0
links=''; commands=''
nl='
'

# Check arity before `shift 2`; without `set -e`, a failed shift would leave the
# option in place and loop forever. An explicitly empty value is not missing.
need_value() {  # need_value <option> <remaining argument count, option included>
  [ "$2" -ge 2 ] || { echo "error: $1 requires a value" >&2; exit 2; }
}

# Hook steps are validated as they are parsed, so an empty value can never hide
# among the newline-terminated entries. Banning empty, `.` and `..` components
# turns "is this the tracker?" into a plain prefix match. `.ai` is refused as
# well, because a link to it would carry .ai/sift into the worktree.
add_link() {  # add_link <path>
  case "$1" in
    *"$nl"*) echo "error: --worktree-link must not contain a newline" >&2; exit 2 ;;
    ''|/*|*/|*//*|.|./*|*/.|*/./*|..|../*|*/..|*/../*|.ai|.ai/sift|.ai/sift/*)
      echo "error: --worktree-link must be a relative path with no empty, . or .. component, outside .ai and .ai/sift: $1" >&2
      exit 2 ;;
  esac
  links="${links}${1}${nl}"
}

# Each command becomes one line of the hook. bash -n rejects text that would
# leave the generated script unparseable, such as an unterminated quote. For an
# unterminated here-document it only warns, yet that heredoc would swallow the
# later steps. Any stderr output is therefore a rejection.
add_command() {  # add_command <command>
  local err
  case "$1" in
    *"$nl"*) echo "error: --worktree-command must not contain a newline" >&2; exit 2 ;;
    *[![:space:]]*) ;;
    *) echo "error: --worktree-command must not be blank" >&2; exit 2 ;;
  esac
  if ! err=$(printf '%s\n' "$1" | bash -n 2>&1) || [ -n "$err" ]; then
    echo "error: --worktree-command is not a complete bash command: $1" >&2; exit 2
  fi
  commands="${commands}${1}${nl}"
}

while [ $# -gt 0 ]; do
  case "$1" in
    --root)           need_value --root "$#";      root="${2:-}"; shift 2 ;;
    --prefix)         need_value --prefix "$#";    prefix="${2:-}"; shift 2 ;;
    --milestone)      need_value --milestone "$#"; milestone="${2:-}"; shift 2 ;;
    --worktree-link)  need_value --worktree-link "$#"; add_link "$2"; shift 2 ;;
    --worktree-command)
                      need_value --worktree-command "$#"; add_command "$2"; shift 2 ;;
    --suggest-prefix) suggest=1; shift ;;
    --suggest-worktree-links) suggest_links=1; shift ;;
    -h|--help)        awk 'NR == 1 { next } /^#/ { print; next } { exit }' "$0"; exit 0 ;;
    *) echo "error: unknown argument: $1" >&2; exit 2 ;;
  esac
done

[ -n "$root" ] || { echo "error: --root is required" >&2; exit 2; }
root=$(cd "$root" 2>/dev/null && pwd -P) || {
  echo "error: --root is not a readable directory" >&2; exit 2; }

# --- Link suggestion (read-only) --------------------------------------------
# git applies its own ignore rules; this only reshapes the answer. Capture it
# first so a git failure exits 2 instead of passing for an empty list. A line
# git had to C-quote starts with `"` and is not a path anyone can pass back.

if [ "$suggest_links" -eq 1 ]; then
  [ "$(git -C "$root" rev-parse --is-inside-work-tree 2>/dev/null)" = true ] || exit 0
  ignored=$(git -C "$root" -c core.quotePath=false \
              ls-files --others --ignored --exclude-standard --directory) || {
    echo "error: git ls-files failed under $root" >&2; exit 2; }
  printf '%s\n' "$ignored" |
    awk '
      /^"/ { next }
      { sub(/\/$/, "") }
      $0 == "" || $0 == ".ai" || $0 == ".ai/sift" || index($0, ".ai/sift/") == 1 { next }
      { print }
    ' |
    LC_ALL=C sort -u
  exit 0
fi

# --- Prefix derivation ------------------------------------------------------

derive_prefix() {
  basename "$1" | tr -cd '[:alnum:]' | tr '[:lower:]' '[:upper:]' | cut -c1-4
}

# Validate the whole prefix. Spell out the ASCII set because bracket ranges are
# locale-collated; `?*` enforces the two-character minimum and length is checked later.
prefix_is_well_formed() {  # prefix_is_well_formed <value>
  case "$1" in
    ''|*[!ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789]*)  return 1 ;;
    [ABCDEFGHIJKLMNOPQRSTUVWXYZ]?*)                return 0 ;;
    *)                                             return 1 ;;
  esac
}

# Validate all path-forming input before the first write.
if [ "$suggest" -eq 1 ]; then
  candidate=$(derive_prefix "$root")           # already <= 4 characters, so length is moot
  prefix_is_well_formed "$candidate" || exit 1
  printf '%s\n' "$candidate"; exit 0
fi

[ -n "$prefix" ] || { echo "error: --prefix is required (use --suggest-prefix for a default)" >&2; exit 2; }
prefix_is_well_formed "$prefix" || {
  echo "error: prefix must be 2-8 uppercase alphanumerics starting with a letter: $prefix" >&2; exit 2; }
[ ${#prefix} -le 8 ] || { echo "error: prefix must be at most 8 characters: $prefix" >&2; exit 2; }
# The milestone is a path component. Validate the whole value and spell out the
# ASCII set to avoid locale-collated bracket ranges.
case "$milestone" in
  ''|-*|*-|*--*|*[!abcdefghijklmnopqrstuvwxyz0123456789-]*)
    echo "error: milestone must be lowercase kebab-case: $milestone" >&2; exit 2 ;;
esac

# --- Locate the skill's assets ----------------------------------------------

here=$(cd "$(dirname "$0")" && pwd -P)
assets="$here/../assets"
[ -d "$assets" ] || { echo "error: skill assets not found at $assets" >&2; exit 2; }
# Print refresh commands with a canonical asset path.
assets=$(cd "$assets" && pwd -P)
[ -f "$assets/README.md" ] || { echo "error: $assets/README.md is missing" >&2; exit 2; }

sift="$root/.ai/sift"
created=''; kept=''; stale=''; orphan=''; orphan_rm=''

note_created() { created="${created}  created  ${1}
"; }
note_kept()    { kept="${kept}  kept     ${1}
"; }
note_stale()   { stale="${stale}  stale    ${1} (differs from the shipped convention)
"; }
# Derive both the finding and removal command from one tree-relative path.
note_orphan()  { orphan="${orphan}  orphan   .ai/sift/${1} (installed, but the shipped convention no longer defines it)
"
                 orphan_rm="${orphan_rm}  rm ${sift}/${1}
"; }

# --- Atomic create-if-absent ------------------------------------------------
# Stage every write beside its destination and publish with `ln`. EEXIST means
# another writer won, so the destination is kept; a bare `mv` would overwrite.
# The sibling temp keeps hard-link and rename publication on one filesystem and
# prevents readers from observing a partially written final path. The `-e` checks
# below are fast paths only, never the create-if-absent arbitration.

# new_temp <directory> — private sibling temp with creation mode restored from
# mktemp's 0600 through `chmod +rw`, which remains masked by the caller's umask.
new_temp() {
  local tmp
  mkdir -p "$1" || return 2
  tmp=$(mktemp "$1/.sift-init.XXXXXX") || return 2
  chmod +rw "$tmp" || { rm -f "$tmp"; return 2; }
  printf '%s\n' "$tmp"
}

# publish <temp path> <relative destination> [keep-symlink] — consumes the temp
# file either way. With keep-symlink, a dangling link that makes ln fail counts as
# occupied, so the mv fallback can never replace it. Other entries leave it off,
# which lets a repeat run repair a dangling README.
publish() {
  if ln "$1" "$sift/$2" 2>/dev/null; then
    rm -f "$1"; note_created ".ai/sift/$2"; return 0
  fi
  if [ -e "$sift/$2" ] || { [ -n "${3:-}" ] && [ -L "$sift/$2" ]; }; then
    rm -f "$1"; note_kept ".ai/sift/$2"; return 0
  fi
  # Filesystems without hard links fall back to an atomic same-directory rename;
  # this path cannot use EEXIST to arbitrate concurrent writers.
  if mv "$1" "$sift/$2" 2>/dev/null; then note_created ".ai/sift/$2"; return 0; fi
  rm -f "$1"
  echo "error: cannot create $sift/$2" >&2
  return 2
}

install_file() {  # install_file <source> <relative destination>
  local tmp
  if [ -e "$sift/$2" ]; then note_kept ".ai/sift/$2"; return 0; fi
  tmp=$(new_temp "$(dirname "$sift/$2")") || {
    echo "error: cannot stage $sift/$2" >&2; return 2; }
  cp "$1" "$tmp" || { rm -f "$tmp"; echo "error: cannot read $1" >&2; return 2; }
  publish "$tmp" "$2"
}

write_file() {   # write_file <relative destination>  (body on stdin)
  local tmp
  if [ -e "$sift/$1" ]; then note_kept ".ai/sift/$1"; cat > /dev/null; return 0; fi
  tmp=$(new_temp "$(dirname "$sift/$1")") || {
    cat > /dev/null; echo "error: cannot stage $sift/$1" >&2; return 2; }
  cat > "$tmp" || { rm -f "$tmp"; echo "error: cannot write $sift/$1" >&2; return 2; }
  publish "$tmp" "$1"
}

# --- Claim the tree ---------------------------------------------------------
# Bare `mkdir` atomically selects one fresh-tree creator. Other writers enter the
# repair path; per-file atomic publication handles their remaining races.

mkdir -p "$root/.ai" || exit 2
if mkdir "$sift" 2>/dev/null; then
  fresh=1
  note_created ".ai/sift/"
else
  fresh=0
  [ -d "$sift" ] || { echo "error: cannot create $sift" >&2; exit 2; }
  note_kept ".ai/sift/ (existing tree — repairing)"
fi

# --- Tracking policy --------------------------------------------------------
# Write the tree-local ignore file only for the fresh-tree winner, before any
# fallible scaffolding write. Its absence on repair is the opt-in to tracking.

if [ "$fresh" -eq 1 ]; then
  write_file ".gitignore" <<'EOF' || exit 2
*
!.gitignore
EOF
else
  if [ -e "$sift/.gitignore" ]; then
    note_kept ".ai/sift/.gitignore"
  else
    note_kept ".ai/sift/.gitignore (absent — tracking is the user's choice)"
  fi
fi

# --- The convention itself, copied never generated --------------------------
# Install shipped README and schemas byte for byte.

install_file "$assets/README.md" "README.md" || exit 2
install_file "$assets/scripts/sift.sh" "scripts/sift.sh" || exit 2
for x in "$assets"/schemas/*.xsd "$assets"/schemas/*.xml; do
  [ -f "$x" ] || continue
  install_file "$x" "schemas/$(basename "$x")" || exit 2
done

# --- Is the installed spec still the shipped one? ---------------------------
# Existing README/schema files may contain operator edits, so drift is reported
# and never repaired implicitly. `cmp -s` supplies a silent byte verdict.

check_drift() {  # check_drift <shipped source> <relative destination> — reads only
  [ -f "$sift/$2" ] || return 0
  cmp -s "$1" "$sift/$2" || note_stale ".ai/sift/$2"
}

check_drift "$assets/README.md" "README.md"
check_drift "$assets/scripts/sift.sh" "scripts/sift.sh"
for x in "$assets"/schemas/*.xsd "$assets"/schemas/*.xml; do
  [ -f "$x" ] || continue
  check_drift "$x" "schemas/$(basename "$x")"
done

# --- And an installed schema the skill no longer ships? ----------------------
# Compare the installed set back to the shipped set. Extra files are `orphan`,
# not `stale`, and are reported with a manual removal command, never deleted.
# Guard the unmatched glob with `[ -f ]`; do not depend on bash `nullglob`.
for x in "$sift"/schemas/*.xsd "$sift"/schemas/*.xml; do
  [ -f "$x" ] || continue
  base=$(basename "$x")
  [ -f "$assets/schemas/$base" ] || note_orphan "schemas/$base"
done

# --- Per-repository configuration -------------------------------------------

write_file "config/config.yaml" <<EOF
# Sift per-repository configuration.
#
# The prefix is immutable. Changing it requires renaming every ticket file and
# rewriting every reference in the same change.
prefix: $prefix
EOF

# --- Worktree setup hook ----------------------------------------------------
# Built with printf only: paths and commands are data, so they never pass
# through a sed replacement. `%q` quotes each path as one bash word. The
# worktree side is spelled `./PATH` so a leading hyphen is never an option.

# The single-quoted formats print $SIFT_ROOT for the hook to expand.
# shellcheck disable=SC2016
worktree_hook_body() {
  local l q c
  printf '%s\n' \
    '#!/usr/bin/env bash' \
    '# Sift worktree setup. prepare-worktree.sh runs this in each new drain worktree,' \
    '# with the worktree as cwd and SIFT_ROOT set to the original checkout.' \
    '# See "## Configuration" in .ai/sift/README.md. Written by sift-init; edit freely.' \
    'set -eu'
  while IFS= read -r l; do
    [ -n "$l" ] || continue
    q=$(printf '%q' "$l")
    printf '\n# Link %s from the original checkout (chosen at sift init).\n' "$l"
    printf 'if { [ -e "$SIFT_ROOT"/%s ] || [ -L "$SIFT_ROOT"/%s ]; } && ! { [ -e ./%s ] || [ -L ./%s ]; }; then\n' \
      "$q" "$q" "$q" "$q"
    printf '  mkdir -p "$(dirname ./%s)"\n' "$q"
    printf '  ln -s "$SIFT_ROOT"/%s ./%s\n' "$q" "$q"
    printf 'fi\n'
  done <<EOF
$links
EOF
  while IFS= read -r c; do
    [ -n "$c" ] || continue
    printf '\n# Project command (chosen at sift init).\n%s\n' "$c"
  done <<EOF
$commands
EOF
}

# install_hook — write_file cannot set a mode, so stage, chmod and publish here.
# `chmod +x` with no "who" is masked by the umask, like the file's other bits.
# A dangling symlink fails `-e` yet is still the project's hook, so test `-L`.
install_hook() {
  local dest="config/worktree-setup" tmp
  if [ -e "$sift/$dest" ] || [ -L "$sift/$dest" ]; then note_kept ".ai/sift/$dest"; return 0; fi
  tmp=$(new_temp "$sift/config") || { echo "error: cannot stage $sift/$dest" >&2; return 2; }
  if ! worktree_hook_body > "$tmp" || ! chmod +x "$tmp"; then
    rm -f "$tmp"; echo "error: cannot write $sift/$dest" >&2; return 2
  fi
  publish "$tmp" "$dest" keep-symlink
}

if [ -n "$links$commands" ]; then
  install_hook || exit 2
fi

write_file "MILESTONES.md" <<EOF
# Milestones

Milestones, in intended order. With a milestone's first ticket, add it here and
create the matching \`open/<milestone>/\` folder.

Rename a milestone by moving every ticket file and updating its \`milestone:\` in
the same change.

## $milestone

Initial backlog for work not assigned to a named milestone.
EOF

# No .gitkeep: this tree ignores itself.
for d in "open/$milestone" "archive"; do
  if [ -d "$sift/$d" ]; then
    note_kept ".ai/sift/$d/"
  else
    mkdir -p "$sift/$d" || exit 2
    note_created ".ai/sift/$d/"
  fi
done

# --- Report -----------------------------------------------------------------

printf 'sift tree at %s\n\n' "$sift"
printf '%s' "$created"
printf '%s' "$kept"

# Print refresh commands here because the installed README may itself be stale.
if [ -n "$stale" ]; then
  printf '%s' "$stale"
  printf '\nReview local changes before refreshing shipped assets:\n\n'
  printf '  cp %q %q\n' "$assets/README.md" "$sift/README.md"
  printf '  cp %q/*.xsd %q/*.xml %q/\n' "$assets/schemas" "$assets/schemas" "$sift/schemas"
  printf '  cp %q %q\n' "$assets/scripts/sift.sh" "$sift/scripts/sift.sh"
fi

# Orphans need an explicit deletion; copying current assets cannot remove them.
if [ -n "$orphan" ]; then
  printf '%s' "$orphan"
  printf '\nThat file is installed under a name this skill no longer ships. Either the\n'
  printf 'convention withdrew the schema — removing one is a breaking change, which is why\n'
  printf 'you are being told — or your repository added a schema of its own, which is\n'
  printf 'yours to keep. Nothing here can tell those two apart, and deleting a file it did\n'
  printf 'not create is the one repair an initializer must never make on its own, so it is\n'
  printf 'left in place and named. Remove it by hand if the convention dropped it:\n\n'
  printf '%s' "$orphan_rm"
fi

printf '\nprefix: %s   first milestone: %s\n' "$prefix" "$milestone"

# Verify the resulting tree through the public gate.
if SIFT_ROOT="$root" "$here/sift-gate.sh" > /dev/null 2>&1; then
  printf 'gate: READY\n'
else
  printf 'gate: NOT READY — run %s from inside %s for details\n' "$here/sift-gate.sh" "$root"
  exit 2
fi
