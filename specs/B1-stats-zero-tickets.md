# B1: Return zeros from /api/stats when there are no tickets

**Kind:** bug
**Evidence:** var/log/dev.log: DivisionByZeroError TicketStats.php:29 (x1); requests/003-dashboard-blank-on-monday.md

## Current behaviour
`GET /api/stats` is handled by `StatsController::__invoke` (src/Controller/StatsController.php:15-17), which returns `TicketStats::summary()`. At src/Service/TicketStats.php:29 `closed_ratio` is computed as `round($closed / $total * 100, 1)` with no guard. When the ticket table is empty, `$total` is 0 and PHP throws `DivisionByZeroError`, so the response is HTTP 500. With at least one ticket the call works (covered by `testStatsWithTickets`).

## Required behaviour
1. `GET /api/stats` with zero tickets returns HTTP 200 with a JSON body `{"total":0,"open":0,"in_progress":0,"closed":0,"closed_ratio":0}`. A `0.0` float is also acceptable for `closed_ratio`; it must be numeric, not null or a string.
2. No `DivisionByZeroError` is thrown or logged for an empty table.
3. With one or more tickets, the response is unchanged: same keys, same counts, and `closed_ratio` is still the percentage of closed tickets rounded to one decimal (for example 1 closed out of 4 gives 25.0).

## Acceptance criteria
- [ ] AC1 `GET /api/stats` on an empty schema returns status 200 and a JSON object with `total`, `open`, `in_progress` and `closed` equal to 0.
- [ ] AC2 `GET /api/stats` on an empty schema returns `closed_ratio` equal to 0 (`assertEquals(0, ...)`), and the value is numeric.
- [ ] AC3 Regression: create 2 tickets by POST, close 1 via `PATCH /api/tickets/{id}/status` with `{"status":"closed"}`, then `GET /api/stats` returns `total` 2, `closed` 1, `open` 1 and `closed_ratio` 50.
- [ ] AC4 Regression: the existing `testStatsWithTickets` still passes unmodified.

## Out of scope
- Do not change the stats JSON keys, the rounding rule, or the route.
- Do not touch the ticket entity, the repository query methods, other controllers, or the dashboard front end.
- Do not alter the existing tests' assertions; only add new ones.
- No new dependencies and no migration.

## Hints for the developer
Guard the ratio computation in `TicketStats::summary()` for `$total === 0` (the service owns the calculation; keep the controller thin).
