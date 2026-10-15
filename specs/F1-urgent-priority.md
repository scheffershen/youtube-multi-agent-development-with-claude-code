# F1: Add 'urgent' priority ranked above high and reject unknown priorities

**Kind:** feature
**Evidence:** requests/002-urgent-priority.md; var/log/dev.log: ticket id 3 stored with priority 'urgent' (app.INFO 'Ticket created')
**Decisions:** D1=A (existing rows untouched, no migration), D2=B (validation plus `by_priority` in stats, no filter, no sort), D3=B (`other` key in `by_priority`). B2-D1=A (same `errors.priority` key and 422).

## Current behaviour
`Ticket::PRIORITIES` is `['low', 'normal', 'high']` (src/Entity/Ticket.php:14) but nothing uses it. `CreateTicketRequest::$priority` only has `#[Assert\NotBlank]` (src/Dto/CreateTicketRequest.php:20-21), so any non-blank string is stored, as with `urgent` (log, ticket id 3). `GET /api/stats` returns only `total`, `open`, `in_progress`, `closed` and `closed_ratio` (src/Service/TicketStats.php:23-31), with no priority data. `GET /api/tickets` takes no query parameters (src/Controller/TicketController.php:30).

## Required behaviour
1. `Ticket::PRIORITIES` is exactly `['low', 'normal', 'high', 'urgent']`, in that order (urgent is last = highest rank). The ranking is only this order; no API sort or rank field is added.
2. `POST /api/tickets` with `priority` set to `urgent` returns 201. The response body and the stored ticket have `priority` equal to `urgent`.
3. `POST /api/tickets` with a `priority` that is not one of the four values returns 422 and a JSON body `{"errors": {"priority": "<message>"}}`. No ticket is stored. Matching is exact and case-sensitive (`HIGH` is rejected).
4. A `priority` that is not a string (for example `5`) returns 4xx JSON and never 500, and nothing is stored. (The 422 shape applies if the Serializer or Validator reports it as a violation.)
5. A missing or blank `priority` still behaves as specified in B2: 422 with `errors.priority`.
6. `low`, `normal` and `high` are still accepted with 201.
7. `GET /api/stats` keeps all existing keys and values unchanged and adds `by_priority`, an object with exactly the keys `low`, `normal`, `high`, `urgent`, `other`, all integers, always present (0 when none).
8. In `by_priority`, `low`/`normal`/`high`/`urgent` count tickets with that exact priority, and `other` counts every ticket whose priority is outside those four. The five values always sum to `total`.
9. Existing rows are not modified or deleted and no migration is added (D1=A). Out-of-list rows are still returned unchanged by `GET /api/tickets` and `GET /api/tickets/{id}`.

## Acceptance criteria
Tests use `KernelBrowser` with JSON bodies, like `tests/Api/TicketApiTest.php`.

- [ ] AC1 `Ticket::PRIORITIES` is identical to `['low', 'normal', 'high', 'urgent']`.
- [ ] AC2 POST `/api/tickets` with a valid title and description and `priority: "urgent"` returns 201; body `priority` is `urgent`; `GET /api/tickets/{id}` returns `urgent`.
- [ ] AC3 POST with `priority: "critical"` returns 422 with body `{"errors": {"priority": <non-empty string>}}`; `GET /api/tickets` then has `count` 0.
- [ ] AC4 POST with `priority: "HIGH"` returns 422 with `errors.priority`; nothing stored.
- [ ] AC5 POST with `priority: 5` returns a status below 500 (400 or 422) with a JSON body, and `GET /api/tickets` count is 0.
- [ ] AC6 POST with `priority` missing, and again with `priority: ""`, returns 422 with `errors.priority` (B2 behaviour preserved).
- [ ] AC7 POST with each of `low`, `normal`, `high` returns 201 with that priority.
- [ ] AC8 GET `/api/stats` on an empty table returns `by_priority` equal to `{"low":0,"normal":0,"high":0,"urgent":0,"other":0}` and `total` 0.
- [ ] AC9 After creating one ticket per priority (low, normal, high, urgent) plus a second urgent, GET `/api/stats` returns `by_priority` `{"low":1,"normal":1,"high":1,"urgent":2,"other":0}` and `total` 5.
- [ ] AC10 (D3) With a ticket inserted directly through the EntityManager with priority `critical` (the API rejects it) plus one `urgent` ticket created via POST, GET `/api/stats` returns `other` 1, `urgent` 1, and the five `by_priority` values sum to `total`.
- [ ] AC11 (D1) The ticket from AC10 with priority `critical` is returned unchanged by `GET /api/tickets/{id}` (`priority` is `critical`).
- [ ] AC12 Existing stats keys (`total`, `open`, `in_progress`, `closed`, `closed_ratio`) keep their values; `testStatsWithTickets` passes unamended.

## Out of scope
- A `?priority=` filter or any sort on `GET /api/tickets` (D2=B); no rank field in ticket JSON.
- A migration or any rewrite or deletion of existing rows (D1=A); the `priority` column definition.
- `PATCH /api/tickets/{id}/status`, `Ticket::STATUSES`, and the existing stats keys and calculations.
- `SimulateTrafficCommand` (it already posts `urgent`, which becomes valid).
- B2 behaviour for missing or blank `title`/`description`, and the 404 behaviour from B3.
- Existing tests must not be amended.

## Hints for the developer
- Add `#[Assert\Choice(choices: Ticket::PRIORITIES)]` on `CreateTicketRequest::$priority`, next to `NotBlank`, and use the same violation-to-`errors.<field>` mapping B2 already provides. `#[MapRequestPayload]` is already wired, so no new dependency is needed.
- Make sure a blank value does not produce a second, conflicting message for the same key, and check how a non-string `priority` is reported (type mismatch in the Serializer versus Validator).
- For `by_priority`, add a repository count method like `countByStatus`, grouped by priority in one query (GROUP BY), then fill the four known keys with 0 and put the remainder in `other`; compute `other` so the sum equals `total`.
- Update the `@return` docblock of `TicketStats::summary()`.
