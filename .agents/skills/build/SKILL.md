---
name: build
description: Build a module (or a whole app) from a URS document. Decomposes the URS into an ordered backlog with a foundation item first, then runs the same spec -> develop + test -> verify loop as /improve for every item in order, filling the URS traceability table. Use when asked to "build from the URS", "implement URS-NNN", or "start the greenfield build".
---

You are the **orchestrator** for a build from a requirements document. You do not write
code, tests or specs yourself. You coordinate the roles in `.Codex/agents/`.

Arguments: `$ARGUMENTS` must contain the URS path (for example
`docs/urs/URS-002-sla-module.md`). Options: `--max-rounds N` (default 3),
`--only F0,U1` (build only these item ids), `--dry-run` (decompose and stop),
`--resume` (skip items already marked PASS in the latest `reports/build-*.md`).

## Step 0: Preconditions

Same as `/improve`: green suite, clean tree. Additionally create a branch
`build/<urs-id>` if not already on it (`git checkout -b build/urs-002`), so the whole
build lands as one reviewable unit.

## Step 1: Decompose

Spawn `urs-decomposer` (foreground) with the URS path. Parse its JSON.

- If `unmapped_requirements` is not empty, stop and show them to the user. A requirement
  nobody owns is a human decision, not something to guess.
- Print the backlog as a table: id, title, evidence, depends_on. With `--dry-run`, stop
  here.
- Save the backlog to `reports/build-<urs-id>-backlog.json` so `--resume` can find it.
- **Human gate: backlog.** Write `approvals/pending/build-<urs-id>.md` with the table
  and handle it exactly as `/improve` handles `improve-round-N.md` (chat question when
  attended, push notification and stop when `--unattended`). With `--resume`, an
  existing `approvals/approved/build-<urs-id>.md` counts as the approval; do not ask
  again unless the backlog changed.

## Step D: Decisions

Identical to `/improve` Step D, for every agent in every step including the decomposer
and the traceability update. In `--unattended` mode a WAITING item stops the whole build
(later items depend on it); `--resume` picks it up once the decision file is in
`approvals/approved/`.

## Step 2: Build each item, in order

Items are built **sequentially** in the order returned, because each depends on the
previous ones landing in the tree. Do not parallelise across items: spec-writing for the
next item may start early (it only reads), but its developer, tester and verifier start
only after the previous item's PASS commit. Inside an item, developer and tester still
run in parallel on the same tree with the `tests/` ownership split stated in both prompts.
The candidate revision the verifier judges is the working tree at that moment; it becomes
the item commit on PASS, so the verdict and the commit always describe the same tree.

For each item, run **exactly** steps 2a to 2e of `/improve` (`.Codex/skills/improve/SKILL.md`)
with these differences:

- **2a (spec).** For `kind: foundation`, tell the spec-writer: "Use
  `specs/templates/foundation.md` as the skeleton and fill every section." For every
  item, pass the `constraints` list and say: "Each constraint is a URS-N requirement that
  applies to this item; turn each into at least one acceptance criterion."
- **2b (build).** Unchanged. The developer must not be told what the tester is doing.
- **2c (verify).** Unchanged, but add to the verifier prompt: "Also confirm every URS id
  in the spec's Evidence line is named in at least one test method's docblock or name."
- **2d (fix loop).** Unchanged. On BLOCKED, write `approvals/pending/blocked-<id>.md`
  as `/improve` does, then **stop the whole build**; later items depend on this one.
- **2e (close).** After the commit, update the URS traceability table (section 7): fill
  the Specification and Test columns with the paths and Status with `PASS` for every URS
  id in the item's evidence. Commit that with the message `trace: <item id>`.
- **2f (publish).** Unchanged: doc-writer and changelog-writer per item. For a
  `foundation` item the doc-writer usually reports nothing to document except new JSON
  keys; that is correct.

## Step 3: Acceptance sweep

The last item (`FX`) is the sweep. Its verifier run doubles as the module acceptance:
after it passes, run `tools/php bin/console app:simulate-traffic --reset` yourself and
confirm zero `HTTP 500` in its summary. If there is one, the sweep's verifier missed it;
spawn a fresh verifier for `FX` with the log line.

## Step 4: Report

Write `reports/build-<urs-id>.md`:

```markdown
# Build <URS id> - <date>

| Item | Title | URS ids | Result | Fix rounds | Decisions | Commit |
|------|-------|---------|--------|-----------|-----------|--------|

## Traceability
Copy of the URS section 7 table as it now stands.

## Open points for the human
Unmapped requirements, BLOCKED items, constraints the verifier noted but could not test.
```

Print the item table and the open points. Do not merge the branch; that is the human
gate.

## Rules

Everything in `/improve`'s Rules section applies. In addition: never reorder items to
work around a BLOCKED one, and never mark a URS id PASS without a verifier PASS on an
item that lists it as evidence.
