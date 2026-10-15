# B2: Reject missing title, description or priority on POST /api/tickets instead of crashing

**Kind:** bug
**Evidence:** var/log/dev.log: ErrorException 'Undefined array key priority' TicketController.php:40 (x1)
**Decisions:** D1=A (422 on missing priority), D2=B (same rule for title and description), D3=A (add property-access and property-info, bind a readonly DTO)

## Current behaviour
`TicketController::create` (src/Controller/TicketController.php:38-40) decodes the body with `json_decode` and reads `$data['title']`, `$data['description']` and `$data['priority']` with no check. When any key is absent, PHP raises an `ErrorException` ("Undefined array key ...") and the response is HTTP 500; the `priority` case is the one in the log, and `title` and `description` fail the same way on the same line. A complete body works and returns 201 (covered by `testCreateAndListTicket`). `Ticket::PRIORITIES` is `['low','normal','high']` (src/Entity/Ticket.php:14) but is not checked on create.

## Required behaviour
1. `POST /api/tickets` with a JSON body missing `priority` returns HTTP 422 and a JSON body `{"errors": {"priority": "<message>"}}`. No ticket is persisted.
2. The same rule applies to `title` and `description`: a field that is absent, `null` or the empty string `""` returns 422 with the key `errors.<field>` set to a non-empty string message.
3. All failing fields are reported in one response. A body `{}` (or an empty body) returns 422 with `errors` containing exactly the keys `title`, `description` and `priority`.
4. On any 422 from this endpoint, no row is inserted (`GET /api/tickets` still shows the previous count) and no "Ticket created" log line is written.
5. No request to this endpoint with a JSON object body may return 500 because of a missing key.
6. A complete valid body (non-empty `title`, `description`, and `priority` one of `low`, `normal`, `high`) still returns 201 with the same JSON shape as today (`id`, `title`, `description`, `priority`, `status` = `open`, `createdAt`).
7. The `errors` object is a flat map of field name to a single string message. This shape is the contract F1 will extend with an "unknown priority" message on the same `errors.priority` key.

## Acceptance criteria
- [ ] AC1 `POST /api/tickets` with `{"title":"T","description":"D"}` (no priority) returns status 422; the JSON body has `errors.priority` as a non-empty string.
- [ ] AC2 `POST /api/tickets` with `{"description":"D","priority":"low"}` (no title) returns 422 with `errors.title` a non-empty string.
- [ ] AC3 `POST /api/tickets` with `{"title":"T","priority":"low"}` (no description) returns 422 with `errors.description` a non-empty string.
- [ ] AC4 `POST /api/tickets` with `{}` returns 422 and `array_keys($body['errors'])` equals, in any order, `['title','description','priority']`.
- [ ] AC5 `POST /api/tickets` with an empty request body returns 422 (not 500) with the same three error keys as AC4.
- [ ] AC6 `POST /api/tickets` with `{"title":"","description":"D","priority":"low"}` returns 422 with `errors.title`, and `errors` does not contain `description` or `priority`.
- [ ] AC7 `POST /api/tickets` with `{"title":"T","description":"D","priority":null}` returns 422 with `errors.priority`.
- [ ] AC8 After any of AC1 to AC7, `GET /api/tickets` returns `count` 0 on a fresh schema (nothing persisted).
- [ ] AC9 Regression: `POST /api/tickets` with a complete valid body returns 201, `status` is `open`, and the response has the keys `id`, `title`, `description`, `priority`, `status`, `createdAt`.
- [ ] AC10 Regression: `testCreateAndListTicket`, `testChangeStatus` and `testStatsWithTickets` still pass unmodified.
- [ ] AC11 Regression: `PATCH /api/tickets/{id}/status` with `{"status":"bogus"}` still returns 422 with the body keys `error` and `allowed` (its shape is not changed to `errors`).

## Out of scope
- Do not add the `urgent` priority and do not validate that `priority` is one of the allowed values: that is F1. A non-empty unknown priority such as `"bogus"` keeps today's behaviour (201, stored) until F1.
- Do not change `PATCH /api/tickets/{id}/status`, `GET /api/tickets`, `GET /api/tickets/{id}` or `/api/stats`, including their error shapes. The 500 on `GET /api/tickets/{id}` for an unknown id is a separate matter.
- Do not specify or change behaviour for malformed JSON, or for values of the wrong JSON type (for example a numeric `priority`). Only missing, `null` and `""` are covered.
- Do not change the `Ticket` entity columns, the repository, or the database schema; no migration.
- Do not alter the assertions of existing tests; only add new ones.
- No new dependencies beyond `symfony/property-access` and `symfony/property-info` (see Hints).

## Hints for the developer
- Bind the body with a `readonly` DTO (constructor property promotion) and `#[MapRequestPayload]` on the action argument; put `#[Assert\NotBlank]` on `title`, `description` and `priority`. Make the DTO properties nullable or give them defaults so that a missing key reaches the Validator and produces a violation instead of a denormalization error. Keep the controller thin.
- `symfony/serializer` and `symfony/validator` are in composer.json (lines 19-20); `symfony/property-access` and `symfony/property-info` are not. I checked: both directories exist under `vendor/`, but `composer.lock` has no package entry for either (it only names them in other packages' require/conflict constraints). Treat them as not installed from Composer's point of view. D3=A puts `composer require symfony/property-access symfony/property-info` in scope. The expected extra diff is composer.json, composer.lock and any Flex recipe output; the verifier must not flag it as drift. Run it with `tools/composer require ...`.
- The default `#[MapRequestPayload]` failure response is not `{"errors":{"<field>":"..."}}`. Convert the `ValidationFailedException` (reachable from the `HttpException` the argument resolver throws) into that shape, for example with an `#[AsEventListener]` on `kernel.exception` limited to this case, or with `validationFailedStatusCode` plus a listener. Use the first message per field. Do not call `json_decode` by hand.
- Test style: reuse `postJson()` and `body()` in tests/Api/TicketApiTest.php.
