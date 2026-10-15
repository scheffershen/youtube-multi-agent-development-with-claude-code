# F2: Filter GET /api/tickets by status and return 400 on an unknown status

**Kind:** feature
**Evidence:** requests/001-filter-tickets-by-status.md; reports/round-1.md: listed as open item F2, not selected in round 1
**Decisions:** D1=C (400 with the B2 `errors` map, message names the allowed values), D2=A (empty `status=` counts as unknown, 400)

## Current behaviour
`TicketController::list` (src/Controller/TicketController.php:29-35) takes no arguments and always returns `findAllNewestFirst()` (src/Repository/TicketRepository.php:20-23), so `?status=open` is ignored and closed tickets are returned too. Response shape is `{"items": [...], "count": N}`. `Ticket::STATUSES` is `['open','in_progress','closed']` (src/Entity/Ticket.php:15). The B2 error listener (src/EventListener/TicketValidationExceptionListener.php:22) is limited to route `ticket_create` and always answers 422.

## Required behaviour
1. `GET /api/tickets?status=<s>` where `<s>` is one of `open`, `in_progress`, `closed` returns 200 with `{"items": [...], "count": N}` containing only tickets whose `status` equals `<s>`. `count` equals the number of items. Ordering stays newest first (same as today).
2. A known status with no matching tickets returns 200 with `{"items": [], "count": 0}`.
3. `GET /api/tickets` with no `status` parameter returns every ticket, exactly as today.
4. `GET /api/tickets?status=<unknown>` (for example `bogus`, or `OPEN`: matching is case-sensitive) returns 400, not 200 and not an empty list. Body: `{"errors": {"status": "<message>"}}`, where `errors.status` is a non-empty string that names the allowed values (`open`, `in_progress`, `closed`). Tests assert the `errors.status` key and that it is a non-empty string, not the exact wording.
5. `GET /api/tickets?status=` (empty value) is treated as unknown: 400 with the same body shape as item 4.
6. The 400 body is a flat map of field to single string message (the B2 `errors` shape). The 422 behaviour of `POST /api/tickets` and the `error`/`allowed` shape of `PATCH /api/tickets/{id}/status` are unchanged.
7. No request to this endpoint with a string `status` value may return 500.
8. The allowed values come from `Ticket::STATUSES`, with no second hard-coded list.

## Acceptance criteria
- [ ] AC1 Create three tickets, set one to `closed` and one to `in_progress` via PATCH. `GET /api/tickets?status=open` returns 200, `count` 1, and every item has `status` `open`.
- [ ] AC2 Same data: `GET /api/tickets?status=closed` returns 200, `count` 1, item `status` `closed`. `?status=in_progress` returns 200, `count` 1, item `status` `in_progress`.
- [ ] AC3 Same data: `GET /api/tickets` (no parameter) returns 200 and `count` 3 (unchanged behaviour).
- [ ] AC4 On a fresh schema, `GET /api/tickets?status=open` returns 200 with `items` equal to `[]` and `count` 0.
- [ ] AC5 With only `open` tickets present, `GET /api/tickets?status=closed` returns 200 with `items` `[]` and `count` 0 (known status, no match, not an error).
- [ ] AC6 `GET /api/tickets?status=bogus` returns 400; the body has `errors.status` as a non-empty string; the body has no `items` key. The message contains each of `open`, `in_progress`, `closed`.
- [ ] AC7 `GET /api/tickets?status=OPEN` returns 400 with `errors.status` (case-sensitive match).
- [ ] AC8 `GET /api/tickets?status=` returns 400 with `errors.status` a non-empty string (D2=A).
- [ ] AC9 With tickets present, `GET /api/tickets?status=bogus` returns 400 and not a list: the unknown status is never answered with the full list or an empty list.
- [ ] AC10 Regression: `POST /api/tickets` with `{}` still returns 422 with `errors` keys `title`, `description`, `priority` (B2 unchanged).
- [ ] AC11 Regression: `PATCH /api/tickets/{id}/status` with `{"status":"bogus"}` still returns 422 with body keys `error` and `allowed`.
- [ ] AC12 Regression: `testCreateAndListTicket`, `testChangeStatus` and `testStatsWithTickets` pass unmodified.

## Out of scope
- Do not change `PATCH /api/tickets/{id}/status`, its 422 status or its `error`/`allowed` shape.
- Do not change `POST /api/tickets` behaviour: it still returns 422 with the `errors` map (B2), not 400.
- Do not change `GET /api/tickets/{id}`, `/api/stats`, or the response shape of a ticket.
- Do not add other filters (priority, text search), pagination, sorting parameters, or a status filter on other routes.
- Do not specify behaviour for a non-string `status` such as `?status[]=open`, beyond item 7 for string values.
- Do not change the `Ticket` entity, the schema or add a migration; do not alter existing test assertions, only add tests.
- Do not change the default ordering (newest first).

## Hints for the developer
- `symfony/property-access` and `symfony/property-info` are already in composer.json (lines 18-19), so `#[MapQueryString]` works with no new dependency.
- Bind the query with `#[MapQueryString]` on a `readonly` DTO (constructor property promotion) with a nullable `status` property, and `#[Assert\Choice(callback: ...)]` or `choices` built from `Ticket::STATUSES`. A null (parameter absent) must pass; an empty string must fail. Use `message` to name the allowed values. Check how the empty string reaches the DTO: a `?status=` value is an empty string, and Choice rejects it, but verify with a test.
- `#[MapQueryString]` failures default to HTTP 404. Set `validationFailedStatusCode: Response::HTTP_BAD_REQUEST` on the attribute and extend `TicketValidationExceptionListener` (or add a sibling listener) so route `ticket_list` is converted to `{"errors": {"<field>": "<first message>"}}` with the status code of the `HttpException` (400) while `ticket_create` keeps 422. Keep the controller thin.
- Filter in the repository (for example a `findByStatusNewestFirst` or an optional argument), not by filtering the full list in PHP.
- Test style: HTTP calls through `KernelBrowser` as in tests/Api/TicketApiTest.php; reuse `postJson()` and `body()`.
