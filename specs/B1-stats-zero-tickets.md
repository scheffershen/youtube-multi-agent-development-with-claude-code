# B1: Return zero stats instead of 500 when the ticket table is empty

**Kind:** bug
**Evidence:**
- `var/log/dev.log`: DivisionByZeroError TicketStats.php:29 (x1) on route "stats"
- `requests/003-dashboard-blank-on-monday.md`

## Current behaviour

`GET /api/stats` is handled by `src/Controller/StatsController.php:14-18`, which returns
`$this->json($stats->summary())`. `App\Service\TicketStats::summary()`
(`src/Service/TicketStats.php:16-31`) counts tickets via `TicketRepository::count([])` and
`countByStatus()` (`src/Repository/TicketRepository.php:25-28`), then computes
`'closed_ratio' => round($closed / $total * 100, 1)` at `src/Service/TicketStats.php:29`.
When the `ticket` table is empty, `$total` is `0`, the division throws
`DivisionByZeroError`, and the request ends as an HTTP 500 (logged as
`request.CRITICAL: Uncaught PHP Exception DivisionByZeroError` in `var/log/dev.log`). The
dashboard renders a red "An error occurred" box until the first ticket is created. With one
or more tickets the endpoint works and returns
`{"total": int, "open": int, "in_progress": int, "closed": int, "closed_ratio": float}`.

## Required behaviour

1. `GET /api/stats` with zero rows in the `ticket` table returns HTTP 200 with
   `Content-Type: application/json`.
2. The JSON body in that case is exactly
   `{"total": 0, "open": 0, "in_progress": 0, "closed": 0, "closed_ratio": 0}`, where
   `closed_ratio` is numerically zero (`0` or `0.0` are both acceptable; it must not be
   `null`, a string, or absent).
3. `GET /api/stats` never throws `DivisionByZeroError`; no `request.CRITICAL` line for
   route `stats` is written to the log for an empty table.
4. With at least one ticket, the response is unchanged from today: HTTP 200 and the same
   five keys, with `closed_ratio` equal to `round(closed / total * 100, 1)` (e.g. 1 closed
   out of 2 tickets gives `closed_ratio: 50`, and 1 open out of 1 gives `closed_ratio: 0`).
5. The response keys and their order (`total`, `open`, `in_progress`, `closed`,
   `closed_ratio`) are the same whether the table is empty or not.

## Acceptance criteria

- [ ] AC1 Empty table: with a freshly created schema and no tickets, `GET /api/stats`
      returns status 200.
- [ ] AC2 Empty table body: the decoded JSON body of AC1 has `total === 0`, `open === 0`,
      `in_progress === 0`, `closed === 0`, and `closed_ratio` equals `0`
      (`assertEquals(0, $stats['closed_ratio'])`), with no other keys.
- [ ] AC3 Regression, no closed tickets: after `POST /api/tickets` with
      `{"title": "A", "description": "a", "priority": "low"}`, `GET /api/stats` returns 200
      with `total === 1`, `open === 1`, `in_progress === 0`, `closed === 0`, and
      `closed_ratio` equal to `0` (this is the existing `testStatsWithTickets` case and must
      keep passing).
- [ ] AC4 Regression, mixed statuses: after creating two tickets via `POST /api/tickets`
      and moving one to `closed` via `PATCH /api/tickets/{id}/status` with
      `{"status": "closed"}`, `GET /api/stats` returns 200 with `total === 2`, `open === 1`,
      `in_progress === 0`, `closed === 1`, and `closed_ratio` equal to `50`
      (`assertEquals(50.0, $stats['closed_ratio'])`).
- [ ] AC5 Recovery: after AC1 (empty table, 200), creating one ticket via
      `POST /api/tickets` and calling `GET /api/stats` again returns 200 with `total === 1`
      (the empty-table path must not cache or poison the result).

## Out of scope

- Do not change `src/Controller/StatsController.php`; the fix belongs in
  `App\Service\TicketStats::summary()`.
- Do not change `src/Entity/Ticket.php`, `src/Repository/TicketRepository.php`, the
  `ticket` schema, or any Doctrine mapping.
- Do not change the JSON shape, key names, key order, or the rounding of `closed_ratio`
  (one decimal) for the non-empty case; existing behaviour with one or more tickets is
  preserved exactly.
- Do not add new keys (e.g. `error`, `empty`) or change the HTTP status for the non-empty
  case.
- Do not touch `/api/tickets` endpoints, the traffic simulator, or the weekly cleanup job.
- Do not rewrite `tests/Api/TicketApiTest.php::testStatsWithTickets`; new tests are added
  alongside it.

## Hints for the developer

- Guard the ratio in `TicketStats::summary()`: when `$total === 0`, use `0.0` for
  `closed_ratio`; otherwise keep `round($closed / $total * 100, 1)`. A ternary or an early
  local variable is enough; no new service, exception listener, or config is needed.
- Keep the method's return-type docblock (`closed_ratio: float`) truthful: returning a
  float `0.0` in the empty case is the simplest way to satisfy both the docblock and
  `assertEquals(0, ...)` in the tests.
- Symfony's `AbstractController::json()` already sets the 200 status and JSON content type;
  nothing in the controller needs to change.
