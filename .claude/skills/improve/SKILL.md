---
name: improve
description: Run one self-improvement round on TicketFlow. Triages logs and the requests/ inbox, then runs spec -> develop + test -> verify for each item with up to three fix rounds, and writes a report. Use when asked to "improve", "fix what the logs show", "process the requests", or by /loop.
---

You are the **orchestrator** for one improvement round. You do not write code or tests
yourself. You coordinate five subagents defined in `.claude/agents/` and you make the
decisions between their steps. Keep your own context small: pass file paths, not file
contents, and ask agents for structured output.

Arguments: `$ARGUMENTS` may contain `--max-items N` (default 2) and `--max-rounds N`
(default 3). `--dry-run` stops after triage and prints the backlog. `--unattended`
means nobody will answer chat (use it under `/loop` and in routines): every human gate
then goes through `approvals/` and a push notification instead of a chat question.

## Step 0: Preconditions

Under `--dry-run` the two checks below are **reported, not enforced**: note their state
in the printed backlog and go on to triage without asking. Paths listed under
"Untracked by policy" in `CLAUDE.md` (the agent-loop folders on a repository that does
not track them yet) never count as a dirty tree; any other uncommitted file does, and a
person decides what to do with it before a real round.

- Confirm `tools/php vendor/bin/phpunit` passes on the current tree. If it does not,
  stop and report; a red baseline makes verification meaningless. **Exception, only when
  a person approved it:** if `reports/baseline-round-N.md` exists (written with
  `python -I .claude/scripts/record_baseline.py <phpunit output> N` and accepted in the
  approved round file), the suite may be red exactly as that file records it. Run the
  suite once, compare the failing tests with the recorded list, stop if a test outside
  the list fails, and pass the baseline path to the verifier and the recorder with every
  item.
- `git status --porcelain` must be clean. If not, stop and ask the user whether to commit
  or stash. (Under `/loop` with no user available: skip the round and report.)
- Create `reports/` and `specs/` if missing. The round number `N` is
  `1 + number of files matching reports/round-*.md`. A round that is **waiting for
  approval writes no report**, so `N` stays the same until the round actually runs; the
  pending or approved file `approvals/*/improve-round-N.md` is the round's identity.
- Resumption order, before triage: if `approvals/approved/improve-round-N.md` exists and
  `reports/round-N.md` does not, skip triage and use the approved table. If
  `approvals/pending/improve-round-N.md` exists, do not triage again: in attended mode
  ask the question again; in `--unattended` mode stop with "still waiting". Only when
  neither exists do you triage.

## Step D: Decisions (applies to every agent call in every step)

Any agent may return a `DECISION NEEDED` block instead of its report (protocol in
`CLAUDE.md`). When one does:

1. Write `approvals/pending/decision-<item>-D<n>.md`: the block verbatim, plus
   `**Decision:**` left blank. Commit it as `gate: decision-<item>-D<n>` (`git add -- approvals`).
2. Attended: ask the person with a single question whose options are exactly the
   agent's A/B/C options (the recommendation first, marked as such), plus "other" for
   free text. Unattended: push notification naming the file, mark the item WAITING in
   `reports/items/<item>/decisions.md`, and stop this item; the next run resumes it.
3. On the answer: move the file to `approvals/approved/`, fill `**Decision:** <letter>
   by <user> on <date> (chat|file) - <note>`, append one line to
   `reports/items/<item>/decisions.md` (`D<n> | <role> | <question> | <letter> | <user> | <date>`),
   commit as `gate: decision-<item>-D<n> decided`.
4. Send the **same agent** `DECISION D<n>: <letter> - <note>` with SendMessage and wait
   for its normal report. Never spawn a fresh agent for the continuation; it would redo
   the work and could re-decide.
5. If the agent that asked was the verifier, the next verifier round is **not** fresh:
   send the decision to the same verifier so it re-runs only what the answer affects,
   and note this exception in the round report.

You, the orchestrator, are bound by the same protocol. A scope flag, a conflict between a
request and a spec, a choice of which item to drop: write the block yourself with
`ROLE: orchestrator` and go through steps 1 to 3. The only choices you make alone are
mechanical ones the skill already fixes (routing a failure by file path, the round cap).

## Step 1: Triage

Spawn `log-analyst` (background: false). Prompt: "Produce the backlog for round N."
Parse its JSON. Take the top `max-items` items. If the list is empty, write
`reports/round-N.md` saying so and stop.

If `--dry-run`, print the backlog as a table, write it with the state of the two
preconditions and the analyst's `ignored` list to `reports/dry-run-round-N-<date>.md`,
and stop: no approvals file, no commit. The report is the memory of the next dry run.

**Human gate: backlog.** Write `approvals/pending/improve-round-N.md` in the format of
`approvals/README.md` with the selected items. Then:

- Attended: ask the user in chat, one question with options approve / reject / edit,
  and wait. On approve, move the file to `approvals/approved/`, append
  `Approved by <user> on <date> (chat)`, commit `gate: improve-round-N approved`, and
  continue. On reject, move it to `rejected/` with their reason and stop. On edit, they
  change the table in the file and move it themselves; re-read it from `approved/`.
- `--unattended`: send a push notification ("Round N backlog awaits approval:
  approvals/pending/improve-round-N.md"), commit the pending file as
  `gate: improve-round-N pending` so the tree stays clean for the clean-tree
  precondition, and stop **without writing a report**. The next invocation finds the
  pending or approved file for the same `N` (see Step 0) and resumes from there.

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

When each reports, save the block verbatim to `reports/staging/<item id>/developer.md`
and `reports/staging/<item id>/tester.md`, **not** yet to the item folder: the verifier
reads `reports/items/<item id>/` (item.json, decisions.md) and must never find the
developer's or tester's story there. Move both files into `reports/items/<item id>/`
right after the verdict is saved (step 2c). That folder is the **evidence store**: the
documentation agents in step 2f read from it and never from your memory.

Ownership of `tests/` is split, and you must say so in both prompts: the developer owns
only shared test infrastructure the spec names explicitly (for example a new
`tests/Api/ApiTestCase.php`); the tester owns every test class, including structural
changes to existing ones that the spec requires (moving a class onto a new base). If you
forget this, the verifier will fail the item on a criterion nobody was assigned.

### 2c. Verify

Spawn `verifier` with only: "Verify `<spec path>`." Do not forward the developer's report.
Verification is **serialized**: never run two verifiers at once in the same working tree,
and do not start the next item's developer or tester until the verdict is in. The
verifier uses its own SQLite file for the traffic replay (see its role); the log file is
shared, which is why it only reads the lines appended during its own run.
Parse the verdict JSON and save it verbatim to `reports/items/<item id>/verdict-<n>.json`
where `<n>` is the verifier round (1 for the first). It must parse: check it with
`python -I -c "import json,sys; json.load(open(sys.argv[1], encoding='utf-8'))" <file>`;
if it does not (a Windows path with unescaped backslashes is the usual cause), send it
back to the verifier to rewrite with forward slashes. Then move the staged developer and
tester reports into the item folder (step 2b). Also write
`reports/items/<item id>/item.json` with the backlog item once, on the first round.

### 2d. Fix loop

Set `round = 1`. While verdict is FAIL and `round < max-rounds`:

- Sort failures: test-file problems (syntax, wrong assertion vs spec) go to the tester;
  everything else goes to the developer.
- Use `SendMessage` to the existing developer and/or tester agent with the failure list,
  verbatim, plus "Fix only these. Re-run the suite."
- Wait, then spawn a **fresh** verifier (never reuse the verifier; a fresh one has no
  memory of what it expected to see).
- `round += 1`.

If still FAIL after `max-rounds`, mark the item BLOCKED and restore a clean baseline
for the next item in this order:

1. Commit what the human needs to decide: the spec, `reports/items/<id>/` and the
   `approvals/pending/blocked-<id>.md` file (next paragraph), as `gate: blocked-<id>`.
2. `git stash push -u -m "blocked-<id>"`: this takes **everything else** the item
   produced (src/, tests/, migrations/, composer.json and lock, config/, new files),
   not only `src/`. Check with `git status --porcelain` that the tree is clean at HEAD
   and `tools/php vendor/bin/phpunit` is green again before starting the next item.
   If the suite is not green at HEAD, stop the round: the baseline was already broken.
3. Note the stash name in the report. The human restores it with `git stash pop`.

**Human gate: blocked item.** Write `approvals/pending/blocked-<id>.md` with the three
verdicts' `failures` side by side and the stash name, offering: fix the spec (say what),
drop the item, or raise the cap once. Attended: ask in chat and act on the answer.
Unattended: push notification and continue with the next independent item.

### 2e. Close the item

On PASS:

- If the round runs on a recorded red baseline and the verdict reports baseline changes
  (listed tests that now pass, or that fail for a new reason), that is a decision for a
  person (Step D): on acceptance, write `reports/baseline-round-N+1.md` from the
  verifier's suite output with `record_baseline.py` and leave round N's file unchanged.

- `git add -- src tests migrations specs reports requests release approvals` (explicit
  paths, never `git add -A`: paths listed under "Never add" in `CLAUDE.md` are a person's
  work in progress and must stay out of every agent commit), then
  `git commit -m "<item id>: <title>"` with the standard attribution
  trailer.
- If the item cited a file in `requests/`, move that file to `requests/done/` and append
  a line `Resolved in round N, commit <sha>` to it. Commit that too.

### 2f. Publish

Only after the PASS commit. In the **same message** spawn, both in the background:

- `doc-writer` with: "Document `<spec path>` (item `<id>`, verdict in
  `reports/items/<id>/`)."
- `changelog-writer` with: "Write the fragment for `<spec path>` (item `<id>`, commit
  `<sha>`)."

Neither receives the developer's report. Wait for both, save their blocks to
`reports/items/<id>/doc-writer.md` and `changelog-writer.md`, then commit everything
under the documentation paths and `release/unreleased/` as `docs: <id>`. If the
doc-writer reports `NOT DOCUMENTED` criteria without a reason that matches the verdict,
send it one message asking why; do not edit the page yourself.

Flash info and the validation dossier are **not** written per item. They are written
per release by `/release`, from the fragments and the evidence store.

## Step 3: Report

Write `reports/round-N.md`:

```markdown
# Round N - <date>

| Item | Title | Result | Fix rounds | Decisions | Commit |
|------|-------|--------|-----------|-----------|--------|

## Remaining CRITICALs (from verifier)
## Requests still open
## Suggestions for the human
```

Then print the table to the user, and nothing else. Do not paste agent transcripts.

## Rules

- Never let the developer see the verifier's expectations before the verifier runs, and
  never let the verifier see the developer's report. That separation is the point.
- Never raise `max-rounds` on your own. Oscillation is a signal for a human.
- Never switch git branches (checkout, cherry-pick onto another branch) while any agent
  is running: agents read the working tree, not a branch. Do infrastructure commits on
  other branches only between items, when no agent is active.
- Never decide for a person. If a report contains "I chose", "I assumed" or "I decided"
  about anything in the decision list, send the agent back: "That is a decision; return
  the DECISION NEEDED block." Do not accept the report.
- Never run `git tag`, `git merge`, `git push` or a deployment. They are denied and a
  hook refuses them with a message; if you see that message, write the request to
  `approvals/pending/` and stop. Do not retry.
- If any agent returns prose instead of the structured block its definition demands,
  send it one message: "Return only the structured block." Do not parse the prose.
