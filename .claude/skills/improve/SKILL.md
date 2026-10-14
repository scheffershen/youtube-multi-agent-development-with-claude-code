---
name: improve
description: Run one self-improvement round on TicketFlow. Triages logs and the requests/ inbox, then runs spec -> develop + test -> verify for each item with up to three fix rounds, and writes a report. Use when asked to "improve", "fix what the logs show", "process the requests", or by /loop.
---

You are the **orchestrator** for one improvement round. You do not write code or tests
yourself. You coordinate five subagents defined in `.claude/agents/` and you make the
decisions between their steps. Keep your own context small: pass file paths, not file
contents, and ask agents for structured output.

Arguments: `$ARGUMENTS` may contain `--max-items N` (default 2) and `--max-rounds N`
(default 3). `--dry-run` stops after triage and prints the backlog.

## Step 0: Preconditions

- Confirm `tools/php vendor/bin/phpunit` passes on the current tree. If it does not,
  stop and report; a red baseline makes verification meaningless.
- `git status --porcelain` must be clean. If not, stop and ask the user whether to commit
  or stash. (Under `/loop` with no user available: skip the round and report.)
- Create `reports/` and `specs/` if missing. Compute the round number as
  `1 + number of files in reports/`.

## Step 1: Triage

Spawn `log-analyst` (background: false). Prompt: "Produce the backlog for round N."
Parse its JSON. Take the top `max-items` items. If the list is empty, write
`reports/round-N.md` saying so and stop.

If `--dry-run`, print the backlog as a table and stop.

## Step 2: For each item, in priority order

### 2a. Spec

Spawn `spec-writer` with the item's JSON. Receive the spec path. Read the spec yourself,
briefly, to confirm it has at least two acceptance criteria. If not, send the spec-writer
one message asking for the missing criteria and wait.

### 2b. Develop and test in parallel

In the **same message**, spawn:

- `developer` with: "Implement `<spec path>`."
- `tester` with: "Write tests for `<spec path>`."

Use `run_in_background: true` for both, then wait for both notifications. Record the two
agent IDs: you will message them in later rounds instead of spawning fresh ones.

### 2c. Verify

Spawn `verifier` with only: "Verify `<spec path>`." Do not forward the developer's report.
Parse the verdict JSON.

### 2d. Fix loop

Set `round = 1`. While verdict is FAIL and `round < max-rounds`:

- Sort failures: test-file problems (syntax, wrong assertion vs spec) go to the tester;
  everything else goes to the developer.
- Use `SendMessage` to the existing developer and/or tester agent with the failure list,
  verbatim, plus "Fix only these. Re-run the suite."
- Wait, then spawn a **fresh** verifier (never reuse the verifier; a fresh one has no
  memory of what it expected to see).
- `round += 1`.

If still FAIL after `max-rounds`, mark the item BLOCKED, keep the spec and tests, and
`git stash` the `src/` changes into a stash named after the spec so the next item starts
from a clean tree. Note the stash name in the report.

### 2e. Close the item

On PASS:

- `git add -A && git commit -m "<item id>: <title>"` with the standard attribution
  trailer.
- If the item cited a file in `requests/`, move that file to `requests/done/` and append
  a line `Resolved in round N, commit <sha>` to it. Commit that too.

## Step 3: Report

Write `reports/round-N.md`:

```markdown
# Round N - <date>

| Item | Title | Result | Fix rounds | Commit |
|------|-------|--------|-----------|--------|

## Remaining CRITICALs (from verifier)
## Requests still open
## Suggestions for the human
```

Then print the table to the user, and nothing else. Do not paste agent transcripts.

## Rules

- Never let the developer see the verifier's expectations before the verifier runs, and
  never let the verifier see the developer's report. That separation is the point.
- Never raise `max-rounds` on your own. Oscillation is a signal for a human.
- If any agent returns prose instead of the structured block its definition demands,
  send it one message: "Return only the structured block." Do not parse the prose.
