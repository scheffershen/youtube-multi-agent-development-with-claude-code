# TicketFlow

Small Symfony 8.1 / PHP 8.4 JSON API used as a multi-agent playground. Read `AGENTS.md`
for Symfony conventions (it is the standard Symfony recipe file and applies in full).

@AGENTS.md

## Toolchain (Windows, no global PHP on PATH)

Always go through the wrappers in `tools/`; they pin the PHP binary and a `php.ini` that
loads sqlite, intl, mbstring, openssl.

| Task | Bash / Git Bash | PowerShell / cmd |
|------|-----------------|------------------|
| Run PHP | `tools/php ...` | `tools\php.cmd ...` |
| Composer | `tools/composer ...` | `tools\composer.cmd ...` |
| Tests | `tools/php vendor/bin/phpunit` | `tools\php.cmd vendor\bin\phpunit` |
| Console | `tools/php bin/console <cmd>` | `tools\php.cmd bin\console <cmd>` |
| Fake traffic (replay for the verifier and the recorder) | `tools/php bin/console app:simulate-traffic` | same |
| Smoke script (alternative replay against a running server) | `bash .claude/scripts/smoke.sh --base http://127.0.0.1:8000` (probes in `.claude/scripts/smoke-probes.txt`; exit 2 = server down, not a verdict) | same, from Git Bash |

Never call bare `php` or `composer`; they are not on PATH here. Inside the container
(`docker compose exec app ...`) `php` is on PATH; the wrappers are for the host only.

Database is SQLite at `var/data_dev.db`; each PHPUnit process gets its own
`var/data_test_<pid>.db` (set up in `tests/bootstrap.php`), so concurrent suite runs by
several agents do not collide. Tests reset the schema themselves. To reset the dev DB use `app:simulate-traffic --reset` (it recreates the
schema in PHP). Avoid `doctrine:schema:drop --force`: the permission classifier blocks it.

## Layout

- `src/` application code. `tests/Api/` HTTP-level tests (`WebTestCase`).
- `var/log/dev.log` what the traffic simulator and real requests write. The log-analyst
  reads it under the Log sources rules below.
- `requests/` human request inbox (one `.md` per request). `requests/done/` after shipping.
- `specs/` one spec per backlog item, written by the spec-writer agent.
  `specs/templates/foundation.md` is the skeleton for a URS foundation item.
- `docs/urs/` requirements documents. `/build <path>` turns one into a module.
- `reports/round-N.md` one report per improvement round, written by the orchestrator.
- `.claude/agents/` the ten roles: six for the code loop (log-analyst, urs-decomposer,
  spec-writer, developer, tester, verifier) and four for publication (doc-writer,
  changelog-writer, flash-info-writer, validation-recorder).
  `.claude/skills/improve/` the maintenance loop, `.claude/skills/build/` the URS build
  loop (same inner steps, sequential items), `.claude/skills/release/` the release cut.
- `docs/user/` end-user documentation (Docusaurus layout mirroring medica-source).
- `release/unreleased/` changelog fragments awaiting a release.
- `reports/items/<id>/` the evidence store: every agent's structured output per item.
  `.claude/workflows/improve.js` the same loop as a deterministic Workflow script.

## Log sources (for the log-analyst)

| Key | Value |
|-----|-------|
| files | `var/log/dev.log` (single file, Monolog, one record per line) |
| channels, levels | `request` at `CRITICAL` and `ERROR`; `WARNING` only when a request points at it |
| size cap | 5 MB; above it, `grep -c` of a known fingerprint is the only allowed operation |
| rotated dumps | none here (one file) |
| origin | local: the traffic simulator and the developer's own requests; counts are frequency, not user impact |
| known noise | none planted; deprecation records are context, not findings |

## Request inbox

`requests/<id>-<slug>.md`, one file per request (see `requests/README.md`); the demo uses
`001`, `002`, `003` as ids. No legacy inbox file. Shipped requests move to
`requests/done/`, which the analyst treats as closed.

## Untracked by policy

Nothing: every agent-loop folder is tracked here, so a dirty tree is a real precondition
failure. (On a repository where the kit is installed uncommitted, list its folders here;
see the starter kit's `CLAUDE.md` template.)

## Documentation map

The documentation agents (doc-writer, changelog-writer, flash-info-writer,
validation-recorder) read every path and convention from this table. To port the roles
to another project, change this table, not the roles.

| Key | Value |
|-----|-------|
| product | TicketFlow |
| language | fr (vouvoiement, present tense) |
| user_pages | `docs/user/` (Docusaurus; frontmatter `sidebar_position`, `id`, `title`) |
| changelog | `docs/user/annexe/change-log/change-log.md` (newest version block first, under `## Historique des versions de l'application`) |
| flash_info_dir | `docs/user/annexe/flash-info/`, file `flash-info-<yyyy-mm>-v<version without dots>.md` |
| validation_dir | `docs/user/annexe/preuves-validation/`, file `dossier-preuves-validation-<version>.md`, id with dashes |
| fragments | `release/unreleased/` (one `<item-id>.md` per shipped item) |
| evidence | `reports/items/<item-id>/` (`item.json`, `developer.md`, `tester.md`, `verdict-<n>.json`, `doc-writer.md`, `changelog-writer.md`) |
| date_format | `14 octobre 2026` in headings |

For medica-source the same table reads: user_pages `documentation/docs/`, changelog
`documentation/docs/Annexe/change-log/change-log.md`, flash_info_dir
`documentation/docs/Annexe/flash-info/`, validation_dir
`documentation/docs/Annexe/preuves-validation/`.

## Decision protocol (every agent)

Agents do not decide; people do. Whenever your task leaves you a choice between options
that lead to materially different results, **stop and hand the choice back** instead of
picking one. Return, in place of your normal report, exactly this block:

```
DECISION NEEDED
ITEM: <item id or "round N">
ROLE: <your role>
ID: D<n>   (n = 1 + number of entries in reports/items/<item>/decisions.md, or 1)
QUESTION: <one sentence, what must be chosen>
WHY IT IS A DECISION: <which rule below applies>
OPTIONS:
  A. <option> -> <consequence for users / API / data / scope>
  B. <option> -> <consequence>
  C. <option> -> <consequence>   (2 to 4 options)
RECOMMENDATION: <letter, one sentence why>   (you may recommend; you may not choose)
WORK SO FAR: <what you have already done that stays valid whatever the answer>
```

Then end your turn. The orchestrator puts the question to a person and sends you back
`DECISION D<n>: <letter> - <any note>`; continue from there and cite the id in your
report (`DECISIONS: D1=B`).

**What counts as a decision** (hand back): anything that changes the API contract
(status codes, JSON keys, accepted values, defaults), the scope (new dependency,
touching a file the spec lists out of scope, adding or dropping a criterion), the data
(migration strategy, default for existing rows, deleting or rewriting data), security or
access, an existing test's assertions, the reading of an ambiguous requirement, or which
of several requests or requirements wins when they conflict. Also: any time you catch
yourself writing "I chose", "I assumed" or "I decided" in a report.

**What is not a decision** (just do it, note it): internal naming, code style, order of
private methods, which assertion helper, how to structure a test fixture, wording of a
log message that no requirement fixes.

Recommendations are welcome and usually followed; the point is that the record shows a
person said yes. Decisions are logged in `reports/items/<item>/decisions.md` and listed
in the validation dossier.

## Human gates

`approvals/` is the durable human-in-the-loop gate (see its README): backlog approval,
agent decisions (`decision-<item>-D<n>.md`), blocked items, releases not fully validated. Tag, merge, push and deploy are denied in
`.claude/settings.json` and refused by `.claude/hooks/human-gate.py`; on that message,
write the request to `approvals/pending/` and stop. Under `/loop` pass `--unattended` so
gates use push notifications instead of chat questions.

## The improvement loop

`/improve` runs one round: triage (logs + requests) -> spec -> dev + test in parallel ->
verify -> up to 3 fix rounds -> commit -> report. See `TUTORIAL.md`. Agents must return
the structured block their definition asks for; the orchestrator does not parse prose.

Commits are made by the orchestrator only after the verifier says PASS.
