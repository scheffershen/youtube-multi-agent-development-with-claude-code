# Approvals gate

A durable, file-based human-in-the-loop gate. The orchestrators write a request file in
`pending/` at every decision that belongs to a person, then stop or skip until that file
has been moved to `approved/` or `rejected/` by a human. The move is the approval; git
history is the audit trail.

```
approvals/
  pending/    requests waiting for a decision
  approved/   moved here by a human, with a trailing "Approved by <name> on <date>" line
  rejected/   moved here by a human, with a reason line
```

## When a request is written

| File | Written by | Decision asked |
|------|-----------|----------------|
| `improve-round-N.md` | `/improve` after triage | build these items this round? |
| `build-<urs-id>.md` | `/build` after decomposition | build this backlog in this order? |
| `decision-<item>-D<n>.md` | any agent, via the orchestrator, when it hits a choice (protocol in `CLAUDE.md`) | pick A, B, C or write your own |
| `ratify-<item>-D<n>.md` | the validation recorder or the orchestrator, for a decision that was taken without a person | ratify, or reverse and reopen the item |
| `blocked-<item>.md` | either loop, after 3 failed fix rounds | fix the spec, drop the item, or raise the cap once? |
| `release-<version>.md` | `/release` when the dossier is not fully validated | ship with these anomalies, or hold? |

Tagging, merging, pushing and deploying are never requested here: they are denied to the
agents outright (see `.claude/settings.json` and `.claude/hooks/human-gate.py`) and done
by a person.

## Attended vs unattended

In an attended session the orchestrator also asks the question in chat, and on a "yes"
moves the file itself and records who answered. Under `/loop` or a cloud routine nobody
answers chat, so the orchestrator sends a push notification naming the file, ends the
round, and the next round starts by checking `approved/`.

## Approving from a phone

Enable Remote Control on the session in the Desktop app, then open it from claude.ai on
the phone: the same chat question appears there. Or, for the file route, move the file
from any git client and the next round picks it up.

## Format of a decision file

```markdown
# D<n>: <question>

**Item:** <id>   **Role:** <who asked>   **Date:** <when>
**Why it is a decision:** <rule from CLAUDE.md>

| Option | Consequence |
|--------|-------------|
| A. ... | ... |
| B. ... | ... |

**Recommendation:** <letter> - <why>
**Work so far:** <what stays valid whatever the answer>
**Decision:** <letter> by <name> on <date> (chat|file) - <note>
```

The same content is appended, one line per decision, to
`reports/items/<item>/decisions.md`, which the validation dossier lists under
"Décisions humaines".

## Format of a request file

```markdown
# <what is asked, one line>

**Context:** round or URS, date, branch, commit.
**Options:** approve | reject | edit (edit the table below, then move to approved/)

| Item | Title | Evidence | Depends on |
|------|-------|----------|-----------|

**Decision:** (filled by the human on move)
```
