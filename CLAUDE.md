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
| Fake traffic | `tools/php bin/console app:simulate-traffic` | same |

Never call bare `php` or `composer`; they are not on PATH here.

Database is SQLite at `var/data_dev.db` and `var/data_test.db`. Tests reset the schema
themselves. To reset the dev DB use `app:simulate-traffic --reset` (it recreates the
schema in PHP). Avoid `doctrine:schema:drop --force`: the permission classifier blocks it.

## Layout

- `src/` application code. `tests/Api/` HTTP-level tests (`WebTestCase`).
- `var/log/dev.log` what the traffic simulator and real requests write. Agents read it.
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

## Human gates

`approvals/` is the durable human-in-the-loop gate (see its README): backlog approval,
blocked items, releases not fully validated. Tag, merge, push and deploy are denied in
`.claude/settings.json` and refused by `.claude/hooks/human-gate.py`; on that message,
write the request to `approvals/pending/` and stop. Under `/loop` pass `--unattended` so
gates use push notifications instead of chat questions.

## The improvement loop

`/improve` runs one round: triage (logs + requests) -> spec -> dev + test in parallel ->
verify -> up to 3 fix rounds -> commit -> report. See `TUTORIAL.md`. Agents must return
the structured block their definition asks for; the orchestrator does not parse prose.

Commits are made by the orchestrator only after the verifier says PASS.
