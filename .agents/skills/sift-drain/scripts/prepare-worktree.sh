#!/usr/bin/env bash
# Prepare one isolated worker branch without checking out the integration branch.
# Tracker paths stay in the original tree; pass SIFT_ROOT explicitly to workers.
#
# Project setup hook: if <root>/.ai/sift/config/worktree-setup exists, where
# <root> is `git rev-parse --show-toplevel` of the calling directory, it must be
# an executable regular file. It runs once in the new worktree, with stdin from
# /dev/null and its stdout sent to stderr, and with these variables set:
#   SIFT_ROOT         the original root
#   SIFT_WORKTREE     the new worktree path, as given
#   SIFT_BRANCH       the new branch
#   SIFT_BASE_COMMIT  the resolved base SHA
#
# Exit codes:
#   0  worktree ready; stdout ends with the worktree=, branch= and base= lines
#   1  bad arguments, existing path, or a hook that is not an executable file
#      (checked before any worktree or branch exists)
#   3  the hook failed; the worktree and branch are kept for inspection and
#      stderr names both and the hook's status
#   other non-zero statuses come from git unchanged
set -euo pipefail
if [ "$#" -ne 3 ]; then
  echo 'usage: prepare-worktree.sh <base-branch> <new-branch> <absolute-new-path>' >&2
  exit 1
fi
BASE=$1
BRANCH=$2
WORKTREE=$3
case "$WORKTREE" in
  /*) ;;
  *) echo 'worktree path must be absolute' >&2; exit 1 ;;
esac
[ ! -e "$WORKTREE" ] && [ ! -L "$WORKTREE" ] || {
  echo "worktree path already exists: $WORKTREE" >&2; exit 1;
}
git check-ref-format --branch "$BASE" >/dev/null
git check-ref-format --branch "$BRANCH" >/dev/null
COMMIT=$(git rev-parse --verify "$BASE^{commit}")
ROOT=$(git rev-parse --show-toplevel)
HOOK="$ROOT/.ai/sift/config/worktree-setup"
if [ -e "$HOOK" ] || [ -L "$HOOK" ]; then
  [ -f "$HOOK" ] && [ -x "$HOOK" ] || {
    echo "worktree setup hook is not an executable file: $HOOK" >&2; exit 1;
  }
else
  HOOK=''
fi
git worktree add -b "$BRANCH" "$WORKTREE" "$COMMIT"
if [ -n "$HOOK" ]; then
  if (cd "$WORKTREE" && SIFT_ROOT="$ROOT" SIFT_WORKTREE="$WORKTREE" \
       SIFT_BRANCH="$BRANCH" SIFT_BASE_COMMIT="$COMMIT" "$HOOK" < /dev/null) >&2; then
    :
  else
    status=$?
    printf 'worktree setup failed (hook exit %s); worktree kept for inspection\nworktree: %s\nbranch: %s\n' \
      "$status" "$WORKTREE" "$BRANCH" >&2
    exit 3
  fi
fi
printf 'worktree=%s\nbranch=%s\nbase=%s\n' "$WORKTREE" "$BRANCH" "$COMMIT"
