# TicketFlow: a multi-agent playground on Symfony

A deliberately imperfect ticket API plus a team of five Claude Code agents that read the
logs and a human request inbox, then fix and extend the app on their own, round after
round.

```
GET    /api/tickets              list
POST   /api/tickets              create  {title, description, priority}
GET    /api/tickets/{id}         show
PATCH  /api/tickets/{id}/status  {status: open|in_progress|closed}
GET    /api/stats                counts + closed ratio
```

Three bugs ship on purpose. Three human requests wait in `requests/`. The agents find all
of them. Read `TUTORIAL.md` for the walkthrough.

## Quick start

```bash
tools/php bin/console doctrine:schema:create
tools/php bin/console doctrine:schema:create --env=test
tools/php vendor/bin/phpunit                   # green baseline
tools/php bin/console app:simulate-traffic     # three 500s land in var/log/dev.log
```

Then, in Claude Code Desktop opened on this folder:

```
/improve --dry-run     # see what the agents would pick
/improve               # one full round
/loop 30m /improve     # keep going on a timer
```

PHP lives at `C:\php\php-8.4.5-nts-Win32-vs17-x64`. Edit `tools/php.cmd`, `tools/php`
and `tools/php.ini` if yours is elsewhere. `vendor/` is not committed: run
`tools/composer install` after cloning.
