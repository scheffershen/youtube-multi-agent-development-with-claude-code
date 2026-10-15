---
name: release
description: Cut a release from the shipped items: merge changelog fragments into a version block, write the flash-info letter, write the validation evidence dossier by replaying the suite and traffic, and commit. Does not tag or merge; that is the human gate. Use when asked to "release", "cut version X", "prepare the flash info", or "write the validation dossier".
---

You are the **orchestrator** for one release. You assemble; you do not write prose
yourself except the mechanical changelog merge. Arguments: `$ARGUMENTS` must contain the
version (`0.2.0`). Options: `--date YYYY-MM-DD` (default today), `--previous X.Y.Z`
(default: the last `### Version` heading in the changelog), `--skip-dossier`.

The commit range for the recorder is `v<previous>..HEAD` when that tag exists
(`git rev-parse -q --verify v<previous>` says so; listing tags is allowed, creating is
not). Without the tag, use the commit that added the previous version block to the
changelog (`git log -1 --format=%H -S "### Version <previous>" -- <changelog path>`).
Before step 1, check that `<version>` is not already a tag or a changelog heading on any
branch (`git tag -l v<version>`, `git grep -l "### Version <version>" --all`): on a demo or
feature branch that re-runs history, pick the next free number instead of colliding.

Read the **Documentation map** in `CLAUDE.md` first; every path below comes from it.

## Step 0: Preconditions

- Clean tree, green suite (`tools/php vendor/bin/phpunit`).
- At least one fragment in the fragments folder. None means nothing to release: stop.
- For every fragment, `reports/items/<item>/verdict-*.json` exists and the latest one is
  PASS. A fragment without a PASS verdict is removed from the release and reported.

## Step 1: Changelog block (mechanical, you do it)

Read every fragment. Group by `section`: `feature` under **Nouvelles fonctionnalités**,
`fix` under **Corrections de bugs**, `technical` under **Améliorations techniques**.
Insert a new block right after the `## Historique des versions` heading:

```markdown
### Version <version> - <date in the house format, e.g. 14 octobre 2026>

**Nouvelles fonctionnalités**

<feature fragment bodies, verbatim, in item-id order>

**Corrections de bugs**

<fix bodies>

**Améliorations techniques**

<technical bodies>
```

Omit an empty section. Do not rewrite fragment text. Commit:
`release: changelog <version>`.

## Step 2: Flash info and dossier, in parallel

In the same message spawn:

- `flash-info-writer` with the version, date and product name. Background.
- `validation-recorder` with the version, date, previous version, and the commit range
  `v<previous>..HEAD` (or the first commit if no tag). Background. Skip with
  `--skip-dossier`.

Wait for both. Each returns a structured block; if either returns prose, ask once for
the block.

## Step D: Decisions

`/improve` Step D applies to the flash-info writer (a skipped fragment is a decision) and
to you. The validation recorder never asks; it reports decisions in section 7 and opens
an anomaly for any decision that lacks a person's answer. If its report shows such an
anomaly, that is your signal to create the ratification request in `approvals/pending/`
before step 4.

## Step 3: Cross-check (you do it, cheaply)

- Every fragment id appears in the flash-info report's `FRAGMENTS USED` or
  `FRAGMENTS SKIPPED` with a reason. A missing id means the letter is incomplete: send
  the writer one message naming the id.
- The dossier's `CONCLUSION` and `ANOMALIES` lines go verbatim into your final report.
  You do not soften them.
- The flash-info writer sets the previous letter's `sidebar_position` from 0 to 1 so
  the new one sorts first. That is its role, not an unrequested edit; do not flag it.

## Step 3b: Human gate when not validated

If the dossier's `CONCLUSION` is "non intégralement validée", write
`approvals/pending/release-<version>.md` with the conclusion paragraph and the anomaly
table copied verbatim, asking: ship with these anomalies, or hold. Attended: ask in chat;
on "hold", stop after step 4 without printing "ready to tag". Unattended: push
notification. Either way the files are committed; only the recommendation changes.

## Step 4: Archive fragments and commit

Move every fragment from the fragments folder to `release/<version>/`. Commit:
`release: v<version> flash info and validation dossier`.

## Step 4b: Pre-merge check (you do it, read-only)

Before printing the report, predict the human's merge:
`git merge-tree --write-tree <target branch> HEAD`. A non-zero exit or any `CONFLICT`
line means the merge will not be clean; list the conflicting paths in the report with a
one-line resolution hint each (for example "docs/user/*: take this branch's versions").
Check the whole tree, never a hand-picked list of files.

## Step 5: Report

Print:

```
Release <version>
Changelog: <n> feature, <n> fix, <n> technical
Flash info: <path>
Dossier: <path> — <CONCLUSION line> — <ANOMALIES line>
Next (human): review the dossier; `git tag v<version>`; merge.
```

## Rules

- Never tag, never merge, never push. A hook refuses these with a message; if you see
  it, stop. The human does it after reading the dossier.
- Never edit the dossier or the flash info yourself. If something is wrong, message the
  agent that wrote it with the specific line.
- If the dossier says "non intégralement validée", the release still exists as a commit
  and the report says so in the first line. The decision belongs to the human.
