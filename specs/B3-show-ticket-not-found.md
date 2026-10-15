# B3: Return 404 for GET /api/tickets/{id} when the ticket does not exist

**Kind:** bug
**Evidence:** var/log/dev.log: Error 'Call to a member function toArray() on null' TicketController.php:54 (x1, GET /api/tickets/9999)

## Current behaviour
`TicketController::show` (src/Controller/TicketController.php:49-55) calls `$this->tickets->find($id)` and immediately calls `$ticket->toArray()` with no null check. For an unknown id `find()` returns `null`, PHP raises an `Error`, and the response is HTTP 500. The sibling action `changeStatus` (same file, lines 60-63) already handles the same situation by returning 404 with the body `{"error": "Ticket not found"}`. The route requires `\d+`, so non-numeric ids already do not match and are not part of this item.

## Required behaviour
1. `GET /api/tickets/{id}` for an id that has no ticket returns HTTP 404 with `Content-Type: application/json` and the body `{"error": "Ticket not found"}`. This is the same status and body as the unknown-id case of `PATCH /api/tickets/{id}/status`, so the controller has one 404 shape.
2. The 404 body uses the singular key `error` (a string), not the `errors` map that B2 introduced for 422 field validation, because it is not tied to a field.
3. `GET /api/tickets/{id}` for an existing ticket still returns 200 with the same JSON shape as today (`id`, `title`, `description`, `priority`, `status`, `createdAt`).
4. A request for an unknown id does not write to the database and does not return 500.

## Acceptance criteria
- [ ] AC1 `GET /api/tickets/9999` on a fresh schema returns status 404.
- [ ] AC2 The AC1 response has a JSON content type and a body equal to `{"error":"Ticket not found"}`.
- [ ] AC3 After creating a ticket (POST, 201) and reading its `id`, `GET /api/tickets/{id}` returns 200 and a body whose `id`, `title`, `description`, `priority`, `status` and `createdAt` keys are present and match the created ticket.
- [ ] AC4 After creating a ticket with id N, `GET /api/tickets/{N+1000}` returns 404 (an unknown id next to existing data).
- [ ] AC5 Regression: `PATCH /api/tickets/9999/status` still returns 404 with body `{"error":"Ticket not found"}`.
- [ ] AC6 Regression: `testCreateAndListTicket`, `testChangeStatus` and `testStatsWithTickets` still pass unmodified.

## Out of scope
- Do not change `GET /api/tickets`, `POST /api/tickets`, `PATCH /api/tickets/{id}/status` or `/api/stats`, including their status codes and error shapes (the 422 shapes from B2 and the PATCH `error`/`allowed` shape stay as they are).
- Do not add a global exception listener or change the 404 shape of unknown routes.
- Do not change the route requirements, the `Ticket` entity, the repository, or the schema; no migration, no new dependency.
- Do not alter the assertions of existing tests; only add new ones.
- The priority validation (`urgent`, unknown values) is not part of this item.

## Hints for the developer
- Add a null check in `show` that returns `$this->json(['error' => 'Ticket not found'], Response::HTTP_NOT_FOUND)`, mirroring `changeStatus`. Do not use `createNotFoundException()`: it would produce the framework's HTML or default error body instead of the required JSON.
- Test style: use `$this->client->request('GET', '/api/tickets/9999')` and the existing `body()` helper in tests/Api/TicketApiTest.php; create fixtures with `postJson()`.
