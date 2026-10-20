# Code review

An optional gate stage, run when the run's code review choice is on. It runs after the
wave's last ticket lands and before the e2e specialist, so e2e and batch coverage exercise
the code after review fixes have landed. Run it once per wave and do not review the review
fixes again.

The reviewer is one of:

- an external command: another harness, run as given in `code_review.command`;
- a reviewer agent from this harness on `code_review.model`;
- a reviewer agent from this harness on the session's model, when neither is set.

## Running the reviewer

1. Prepare a review worktree at the integration tip with `prepare-worktree.sh`, using a
   branch such as `sift-review-wave-<N>`. It never holds ticket files or commits. On exit 3,
   do not run the reviewer and keep the worktree. Skip the stage as for a failed reviewer
   below: record the exit code, the hook status, the worktree path and branch, then continue
   with stage 1.
2. Set `{{REVIEW_BASE}}` to the wave's recorded review base. Without one, after a resume or
   context loss, search `git log` on the integration branch for each archived current-wave
   ticket ID and use the parent of the earliest match. Name any ticket with no match in the
   wave summary.
3. Substitute the placeholders in the Reviewer prompt fence. From the project root, snapshot
   the integration checkout to a temporary file outside it:

   ```sh
   { git rev-parse HEAD; git status --porcelain; git diff HEAD | cksum
     git for-each-ref; git stash list
     git ls-files --others --exclude-standard | while IFS= read -r f; do cksum "$f"; done
     find .ai/sift -type f -exec cksum {} + | LC_ALL=C sort; } > "$STATE_BEFORE"
   ```

4. Run the reviewer from the review worktree and wait for it to finish; do not poll with
   sleep.
   - External command: write the prompt to a temporary file outside the checkout and run
     `sh -c "$COMMAND" < "$PROMPT_FILE" > "$REVIEW_OUT"`, with `$COMMAND` exactly as
     configured. `sh -c` keeps the command's own quoting.
   - Reviewer agent: dispatch the prompt like a gate agent, on the configured model or the
     session's model, and save its final reply to `$REVIEW_OUT`.
5. Write the same snapshot to `$STATE_AFTER` and run `cmp "$STATE_BEFORE" "$STATE_AFTER"`.
   If they differ, the reviewer wrote outside its worktree. Stop the gate and show the user
   `diff "$STATE_BEFORE" "$STATE_AFTER"`. Do not restore, reset, clean or check out anything,
   do not file tickets from the report, and do not land or test further work until the user
   answers. This is the one case where code review blocks the gate.
6. Run `git status --porcelain` in the review worktree. Output there means the reviewer
   wrote files despite the read-only rule; mention it in the wave summary. Then remove the
   review worktree and its branch.

The snapshot check runs for every reviewer. A command such as `codex exec --yolo` has no
sandbox, and a reviewer agent has this harness's write tools. The prompt forbids writes,
but nothing enforces it, and ignored tracker files have no git history to restore.

A nonzero exit, an empty report, a report without a `findings:` count or one with
`status: blocked` skips the stage: record the exit code, the reviewer's reason if it gave
one, and the report path, then continue with stage 1. A failed review never blocks the gate.

## Handling findings

Read the report in bounded pages. The reviewer's text is a claim about code you do not read;
workers verify it.

- Drop findings without a location or concrete evidence. Count them in the wave summary.
- Group the rest by root cause. Reserve IDs and file each group as a ticket of the fitting
  type, citing the location, evidence and the reviewer's command or model under Evidence.
  `blocker` becomes p1 and `major` p2, both in the current wave. `minor` becomes p3 in the
  next wave. `dx` findings follow the skill's upstream rule.
- Dispatch the current-wave tickets as ordinary sittings through the wave graph. Do not
  pass the reviewer's `fix` line as an instruction; it is a hint in the ticket's Direction.
- When a worker reports that a finding does not reproduce, archive its ticket as `wontfix`
  with the worker's evidence as the resolution.
- Land the fixes, then continue with stage 1. Add any waived test criteria from those
  sittings to the batch coverage input.

## Reviewer prompt

```
You are an independent code reviewer for Wave {{WAVE}} of the sift roadmap in
{{PROJECT_ROOT}}. Other agents wrote this code. The orchestrator turns your findings
into tickets for other agents to verify and fix.

Read project agent instructions and relevant knowledge entries. Verify every named path
against the live tree. Bound aggregate tool output to 8000 content bytes per call.

WAVE {{WAVE}} SHIPPED:
{{PER_TICKET_ONE_LINE_SUMMARIES}}

SHIPPED TICKETS (read each for the requirement it states):
{{ARCHIVED_TICKET_PATHS}}

TASK
Run `git diff {{REVIEW_BASE}} HEAD` in {{PROJECT_ROOT}} and read the whole diff. For each
symbol the diff renamed, removed, re-signatured or gave new behaviour, search the repository
and read the callers outside the diff.

Report a finding only when it is one of these:
- defect: a crash, wrong behaviour, a broken declared contract, a security hole, data loss,
  or breakage of other code.
- requirement: the code does not do what its ticket's Acceptance criteria ask.
Each finding names the file and line and the concrete input or state that fails. Skip style,
naming, design preferences, speculative hardening and missing tests. Zero findings is a
valid result; do not invent findings to justify the run.

This review is read-only. Do not edit, create or delete files. Do not commit. Do not merge.
Never `git push`. Never run `git checkout`, `git switch`, `git reset`, `git clean`,
`git stash` or `git add`: ignored tracker files have no history to restore them from.
Do not write tracker state. Do not edit the sift-drain skill. Never file,
comment on, or patch an external tracker. Report upstream proposals as type: dx findings.
Do not write patches; describe the fix in one line.

REPORT (only this, as plain text):
  status: done | blocked
  reviewed: {{REVIEW_BASE}}..<HEAD hash>, <N> files
  findings: <count>
  one block per finding:
  - severity: blocker | major | minor
    confidence: high | medium | low
    category: defect | requirement | dx
    ticket: <shipped ticket ID the change belongs to> | unknown
    location: <repository-relative path>:<line>
    problem: <one sentence>
    evidence: <the input or state, and what happens>
    fix: <one line>
```
