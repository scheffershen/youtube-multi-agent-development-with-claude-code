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
- `reports/round-N.md` one report per improvement round, written by the orchestrator.
- `.claude/agents/` the five roles. `.claude/skills/improve/` the orchestration loop.
  `.claude/workflows/improve.js` the same loop as a deterministic Workflow script.

## The improvement loop

`/improve` runs one round: triage (logs + requests) -> spec -> dev + test in parallel ->
verify -> up to 3 fix rounds -> commit -> report. See `TUTORIAL.md`. Agents must return
the structured block their definition asks for; the orchestrator does not parse prose.

Commits are made by the orchestrator only after the verifier says PASS.
