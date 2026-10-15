# TicketFlow: a multi-agent playground on Symfony

A deliberately imperfect ticket API plus a team of ten Claude Code agents that read the
logs, a human request inbox and requirements documents, then specify, build, test, verify,
document and release the app on their own, with human gates where a person must decide.

```
GET    /api/tickets              list (?status=, ?breached=)
POST   /api/tickets              create  {title, description, priority}
GET    /api/tickets/{id}         show
PATCH  /api/tickets/{id}/status  {status: open|in_progress|closed}
PATCH  /api/tickets/{id}/assignee {agentId}
GET    /api/agents, POST /api/agents, PATCH /api/agents/{id}
GET    /api/stats, GET /api/reports/sla
```

**The tutorial lives in its own repository:** `../ticketflow-tutorial` (TUTORIAL.md,
a reusable starter kit of the roles and skills, and the video outline).

## Branches

| Branch | What it holds |
|--------|---------------|
| `main` | Pristine demo: three planted 500s, open requests, all agent infrastructure |
| `demo/round-1-example` | One bug fixed through all roles by `/improve` |
| `build/urs-002` | The whole SLA module built from `docs/urs/URS-002-sla-module.md`, releases 0.2.0 and 0.3.0 with flash infos and validation dossiers, not tagged |

## Run it in a container

```bash
docker compose up --build -d          # builds php:8.4 image, migrates, serves on :8000
curl http://localhost:8000/api/stats
docker compose exec app php bin/console app:simulate-traffic --reset
docker compose run --rm app test      # PHPUnit inside the container
docker compose down
```

`var/` is mounted from the host, so `var/log/dev.log` and the SQLite database are the
same files the agents read. `src/`, `config/`, `migrations/`, `tests/` and `public/` are
mounted too, so edits are live; `vendor/` stays in the image (rebuild after
`composer require`). The entrypoint accepts `serve` (default), `test`, or any command.

## Quick start (host PHP)

```bash
tools/php bin/console doctrine:migrations:migrate -n
tools/php bin/console doctrine:migrations:migrate -n --env=test
tools/php vendor/bin/phpunit                          # green baseline
tools/php bin/console app:simulate-traffic --reset    # three 500s land in var/log/dev.log
```

Then, in Claude Code Desktop opened on this folder (new session so the roles register):

```
/improve --dry-run                       # see what the agents would pick
/improve                                 # one full round, with the approval gate
/build docs/urs/URS-002-sla-module.md    # build a module from a requirements document
/release 0.4.0                           # changelog, flash info, validation dossier
```

PHP lives at `C:\php\php-8.4.5-nts-Win32-vs17-x64`. Edit `tools/php.cmd`, `tools/php`
and `tools/php.ini` if yours is elsewhere. Composer is not committed: download
`composer.phar` from https://getcomposer.org/download/ into `tools/` (the wrappers expect
`tools/composer.phar`), then run `tools/composer install`; `vendor/` is not committed
either. On Linux or in the container, system `composer` works as usual.
