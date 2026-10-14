# FX: URS-N acceptance sweep: JSON errors with no 500 under the standard traffic, audit log entries, backward compatibility

**Kind:** acceptance (bug sweep over three pre-existing 500s plus cross-cutting checks)
**URS:** docs/urs/URS-002-sla-module.md, sections 5 and 6
**Evidence:** none in the backlog item (sweep). Observed: `var/log/dev.log` `request.CRITICAL`
lines `DivisionByZeroError ... TicketStats.php line 29`, `Undefined array key "priority" ...
TicketController.php` (create), `Call to a member function toArray() on null ...
TicketController.php` (show); the same three entries are the `remaining_criticals_out_of_scope`
of every verdict since R1 (for example `reports/items/U4/verdict-1.json`). Related human
requests: `requests/002-urgent-priority.md` (any string stored as priority),
`requests/003-dashboard-blank-on-monday.md` (stats fails on an empty table).
**Constraints:** URS-N-01, URS-N-02, URS-N-03 (section 5); section 6 acceptance (standard
traffic replay produces no HTTP 500)
**Depends on:** F0, U1, U2, U3, U4 (shipped), U5 (in flight, see "Out of scope")

## Current behaviour

`TicketController::create()` (`src/Controller/TicketController.php:57-69`) still decodes the
body by hand: `json_decode($request->getContent(), true) ?? []` (line 60) and then
`new Ticket($data['title'], $data['description'], $data['priority'])` (line 62). A body
without one of the three keys raises `Undefined array key` (an `ErrorException` in dev),
which is not an `HttpExceptionInterface`, so `ApiExceptionListener`
(`src/EventListener/ApiExceptionListener.php:24`) leaves it alone and the kernel answers
**500**; the same happens for a malformed or empty body (`json_decode` yields `null`, the
`?? []` fallback leads to the same undefined keys). Any string is accepted as `priority`
(`Ticket::PRIORITIES = ['low', 'normal', 'high']`, `src/Entity/Ticket.php:14`, is not
enforced anywhere; `requests/002-urgent-priority.md` reports exactly that). `show()`
(lines 71-77) calls `$this->tickets->find($id)` and then `$ticket->toArray()` without a null
check: `GET /api/tickets/9999` is a **500** (`Error: Call to a member function toArray() on
null`), whereas `changeStatus()` (lines 82-85) and `assign()` (lines 107-110) already answer
`{"error": "Ticket not found"}` with 404. `TicketStats::summary()`
(`src/Service/TicketStats.php:16-31`) computes `round($closed / $total * 100, 1)` at line 29
with no guard, so `GET /api/stats` (`src/Controller/StatsController.php:14-18`) on an empty
table throws `DivisionByZeroError` and is a **500**. The standard traffic scenario
(`src/Command/SimulateTrafficCommand.php:52-65`) hits all three: `GET /api/stats` right after
`--reset` (line 53, empty table), a POST without `priority` (line 56), `GET /api/tickets/9999`
(line 60); it also posts `priority: 'urgent'` (line 57, stored as-is today) and
`status: 'done'` (line 62, already a controlled 422). `symfony/http-kernel`'s `ErrorListener`
logs every non-HTTP exception and every 5xx at `critical`, and 4xx `HttpExceptionInterface`
at `error` (`vendor/symfony/http-kernel/EventListener/ErrorListener.php:191-195`); in dev the
`main` handler streams everything from `debug` to `var/log/dev.log`
(`config/packages/monolog.yaml:8-12`), which is why the three faults appear as
`request.CRITICAL` lines. The conventions this sweep must copy already exist:
`AgentController::create()` binds its body with
`#[MapRequestPayload(acceptFormat: 'json')] CreateAgentRequest $payload`
(`src/Controller/AgentController.php:41`) on a `final readonly` DTO with empty-string
defaults and `#[Assert\...]` on promoted properties (`src/Dto/CreateAgentRequest.php:13-26`);
`ApiExceptionListener` renders the resolver's 422 as `{"errors": {field: message}}`, and its
400/415/404/405 as `{"error": message}`. The audit log half of URS-N-01 is implemented and
tested: `Ticket assignee changed` with `['id' => $id, 'assignee' => $payload->agentId]`
(`TicketController.php:126`, `tests/Api/U3TicketAssigneeTest.php:329-373`, AC18-AC20) and
`Agent activation changed` with `['id' => $id, 'active' => $payload->active]`
(`AgentController.php:74`, `tests/Api/U2AgentActiveFlagTest.php:301-359`, AC13-AC16). The
ticket JSON has eight keys, `id, title, description, priority, status, createdAt, dueAt,
assignee` (`Ticket::toArray()`, lines 111-123), asserted by every shipped test class.
`tests/Api/F0SlaFoundationTest.php:210-219` (AC11, URS-F-03.2) currently POSTs
`priority: 'urgent'` and expects **201** with a 24 h due time; that assertion conflicts with
the priority validation required below and is amended by this item (Required behaviour 10).
`composer.json` already requires `symfony/serializer`, `symfony/validator`,
`symfony/property-access` and `symfony/property-info` (lines 18-22), so `#[MapRequestPayload]`
works without any new package.

## Required behaviour

1. **`POST /api/tickets` is bound through a DTO (URS-N-02, AGENTS.md).** `create()` takes
   `#[MapRequestPayload(acceptFormat: 'json')] CreateTicketRequest $payload` and no longer
   calls `json_decode()`. New `src/Dto/CreateTicketRequest.php`: `final readonly`,
   constructor property promotion, properties `title`, `description`, `priority`, all
   `string` with an empty-string default (same pattern as `CreateAgentRequest`), with
   constraints: `title` `#[Assert\NotBlank(normalizer: 'trim')]` and
   `#[Assert\Length(max: 120)]` (the column is `length: 120`, `Ticket.php:24`);
   `description` `#[Assert\NotBlank(normalizer: 'trim')]`; `priority`
   `#[Assert\NotBlank]` and `#[Assert\Choice(choices: Ticket::PRIORITIES)]`. Unknown keys in
   the body are ignored. Values are stored as sent (no trimming of the stored value).
2. **Happy path unchanged (URS-N-03).** A body `{"title": "<non-blank, <= 120 chars>",
   "description": "<non-blank>", "priority": "low"|"normal"|"high"}` still returns **201**
   with `Content-Type: application/json` and exactly `Ticket::toArray()` (eight keys in the
   existing order, `status` `open`, `assignee` `null`, `dueAt` per `Ticket::slaTargetHours()`),
   and still writes the existing `Ticket created` info line with `id` and `priority`
   (`TicketController.php:66`).
3. **Validation faults are 422 JSON (URS-N-02).** Each of the following returns **422**,
   `Content-Type: application/json`, body `{"errors": {"<field>": "<non-empty message>"}}`
   keyed by the offending field(s) only, and nothing is persisted (`GET /api/tickets` keeps
   its previous `count`):
   - `priority` missing, `null`, not a string, empty, or not exactly one of `low`, `normal`,
     `high` (so `urgent`, `High`, `LOW`, `""`, `123` all fail) -> key `priority`;
   - `title` missing, `null`, not a string, empty or whitespace only, or longer than
     120 characters -> key `title`;
   - `description` missing, `null`, not a string, empty or whitespace only -> key `description`;
   - a body `{}` -> all three keys `title`, `description`, `priority` present in `errors`.
   Exact message texts are not prescribed.
4. **Decision on `urgent` (requests/002, URS-F-03.2).** This sweep does **not** add `urgent`:
   `Ticket::PRIORITIES` stays `['low', 'normal', 'high']` and `POST /api/tickets` with
   `priority: 'urgent'` is a **422** on `priority` (point 3). Reasons, to be recorded in the
   report: (a) request 002 names no SLA target, so under URS-F-03.2 an `urgent` priority
   added now would receive the **normal 24 h target** (`Ticket::slaTargetHours('urgent')`
   is 24 today and would stay 24 unless `SLA_TARGET_HOURS` is extended), i.e. a looser
   target than `high` (8 h) for a priority meant to rank above it, which contradicts the
   request's intent; (b) adding a value to `Ticket::PRIORITIES` changes the row set of the
   in-flight `GET /api/reports/sla` (U5 iterates `Ticket::PRIORITIES`). Request 002 therefore
   stays in `requests/` (not moved to `done/`) and needs its own item that states the SLA
   target explicitly; the half of request 002 that says "anything not in the list is
   rejected with a validation error, not stored" is satisfied by point 3 for the current
   list. Rows already stored with `priority = 'urgent'` are not touched and keep being
   served as-is by `GET /api/tickets` and `GET /api/tickets/{id}`.
5. **Other client faults on `POST /api/tickets` (URS-N-02).** A body that is not valid JSON
   (for example `{not json`) or an empty body returns **400**; a body sent with a non-JSON
   `Content-Type` (for example `text/plain`) returns **415**. Both have
   `Content-Type: application/json` and `{"error": "<non-empty message>"}` (rendered by the
   existing `ApiExceptionListener`, same as `/api/agents`). Never HTML, never 500.
6. **`GET /api/tickets/{id}` unknown id (URS-N-02).** When `{id}` matches `\d+` but no ticket
   has that id (including `0`), `show()` returns **404** with body exactly
   `{"error": "Ticket not found"}` (the string already used at `TicketController.php:84` and
   `:109`), returned from the action with `$this->json(...)`, not thrown. A non-numeric
   `{id}` keeps falling through the route requirement to the listener's JSON 404. Known ids
   keep returning 200 with `Ticket::toArray()`.
7. **`GET /api/stats` on an empty table (URS-N-02, request 003).** `TicketStats::summary()`
   never divides by zero: with no tickets it returns `total 0, open 0, in_progress 0,
   closed 0, closed_ratio 0` and the endpoint answers **200** with exactly the five keys in
   that order; `closed_ratio` is the JSON number `0` (under the current encoding options a
   PHP `0.0` is emitted as `0`; both `0` and `0.0` are acceptable in PHP, the test compares
   with `assertEquals`). With tickets the values are unchanged (`closed / total * 100`
   rounded to one decimal). No key added, no key removed, no change to `StatsController`.
8. **Standard traffic produces no 500 (URS section 6).** After the change,
   `tools/php bin/console app:simulate-traffic --reset` prints exactly `HTTP 200: 6`,
   `HTTP 201: 2`, `HTTP 404: 1`, `HTTP 422: 3` and no `HTTP 5xx` line; per request the
   statuses are, in scenario order: 200, 201, 201, 422 (no priority), 422 (`urgent`), 200,
   200, 404 (`/9999`), 200, 422 (`done`), 200, 200. The run appends **zero** `CRITICAL` lines
   to `var/log/dev.log`. Two `request.ERROR` lines for the two `HttpException` 422s raised by
   the payload resolver are expected and acceptable (same as `/api/agents` today); the 404 of
   point 6 and the 422 of `changeStatus` are returned by the actions and log nothing.
   `SimulateTrafficCommand` itself is not modified: the scenario is the acceptance fixture.
9. **Audit log, cross-cutting (URS-N-01).** Assignment, unassignment and activation change
   keep writing exactly one `info` record each with the id and the new value: message
   `Ticket assignee changed`, context `['id' => <ticket id>, 'assignee' => <agent id|null>]`;
   message `Agent activation changed`, context `['id' => <agent id>, 'active' => <bool>]`.
   No code change is expected here (U2 AC13-AC16 and U3 AC18-AC20 already cover it); this
   item adds one sweep-level test that exercises the three operations in a single container
   so the requirement has a test traceable from the sweep as well.
10. **Backward compatibility (URS-N-03).** `tests/Api/TicketApiTest.php` passes **unchanged**.
    Every pre-existing ticket JSON key is still present on every ticket endpoint (`POST`,
    `GET` list, `GET /{id}`, `PATCH /{id}/status`, `PATCH /{id}/assignee`) and the stats keys
    are unchanged. `PATCH /api/tickets/{id}/status` keeps its `json_decode()` and its bodies
    byte for byte (`{"error": "Invalid status", "allowed": [...]}` 422, `{"error": "Ticket not
    found"}` 404) because U3 AC21 asserts them. `GET /api/tickets` (status and breached
    filters) and all `/api/agents*` endpoints are untouched. The **only** shipped test that
    changes is `tests/Api/F0SlaFoundationTest.php::testAc11UrsF0302UnknownPriorityFallsBackToNormalTarget`:
    its HTTP part (lines 212-214, POST `urgent` -> 201 and 86400 s) is replaced by "POST
    `urgent` returns 422 with `errors.priority`"; its entity-level assertions (lines 216-218,
    `Ticket::slaTargetHours('urgent'|''|'LOW') === 24`) stay as they are, since URS-F-03.2's
    fallback remains in the entity for any priority added later. Every other test class
    stays green without edits.
11. **Controller stays thin, nothing else moves.** `TicketController::create()` becomes: map
    DTO to `new Ticket(...)`, persist, flush, log, return 201. No `SerializerInterface`,
    `ValidatorInterface` or `json_decode()` call in `create()`; no change to
    `ApiExceptionListener` (it must keep handling only `HttpExceptionInterface`, so a future
    non-HTTP bug still surfaces as a 500 in the logs instead of being masked); no catch of
    `\Throwable` anywhere.

## Acceptance criteria

Each criterion is one test method in a new `tests/Api/FXUrsNAcceptanceSweepTest.php`
extending `ApiTestCase` (use `postJson()`, `patchJson()`, `body()`, `createAgent()`,
`createTicket()`; read the header with `$this->client->getResponse()->headers->get('Content-Type')`).
`tests/Api/TicketApiTest.php` must stay green unchanged; the only edit to an existing test
is AC13 below. The whole suite (`tools/php vendor/bin/phpunit`) must pass.

- [ ] AC1 (URS-N-02) Missing priority is a JSON 422, not 500: `postJson('/api/tickets',
      ['title' => 'Export CSV is empty', 'description' => 'Exported file has headers only.'])`
      (the scenario's body) returns 422, `Content-Type` starting with `application/json`, a
      body with exactly the top-level key `errors`, `errors.priority` a non-empty string, no
      `errors.title`, no `errors.description`; `GET /api/tickets` then returns `count === 0`.
- [ ] AC2 (URS-N-02) Unknown priority is a 422 and `urgent` is not added: for each of
      `'urgent'`, `'High'`, `'LOW'`, `''`, `null`, `123`, `postJson('/api/tickets',
      ['title' => 'Payment double charged', 'description' => 'Customer #4411 charged twice.', 'priority' => $value])`
      returns 422 with `errors.priority` a non-empty string and no other key in `errors`;
      afterwards `GET /api/tickets` returns `count === 0` and `Ticket::PRIORITIES` is exactly
      `['low', 'normal', 'high']`.
- [ ] AC3 (URS-N-02) Invalid title and description: for each body
      `['description' => 'd', 'priority' => 'low']` (no title),
      `['title' => '', 'description' => 'd', 'priority' => 'low']`,
      `['title' => '   ', 'description' => 'd', 'priority' => 'low']`,
      `['title' => str_repeat('x', 121), 'description' => 'd', 'priority' => 'low']`,
      `['title' => 123, 'description' => 'd', 'priority' => 'low']` the response is 422 with
      `errors.title` a non-empty string and no `errors.priority`; for each body
      `['title' => 'T', 'priority' => 'low']` (no description),
      `['title' => 'T', 'description' => '', 'priority' => 'low']`,
      `['title' => 'T', 'description' => null, 'priority' => 'low']` the response is 422 with
      `errors.description` a non-empty string and no `errors.priority`. The raw body `{}`
      (`$this->client->request('POST', '/api/tickets', server: ['CONTENT_TYPE' => 'application/json'], content: '{}')`)
      returns 422 with all three keys `title`, `description`, `priority` in `errors`.
      `GET /api/tickets` then returns `count === 0`.
- [ ] AC4 (URS-N-03) Valid create is unchanged: `postJson('/api/tickets', ['title' => 'Login page shows blank screen', 'description' => 'After the 2.3 deploy, Safari users see nothing.', 'priority' => 'high'])`
      returns 201, `Content-Type` starting with `application/json`, `array_keys($body)` exactly
      `['id', 'title', 'description', 'priority', 'status', 'createdAt', 'dueAt', 'assignee']`,
      `title`, `description`, `priority` echoed as sent, `status === 'open'`,
      `assignee === null`, and `dueAt - createdAt` equals 28800 s (8 h, `high`). An extra
      unknown key (`'colour' => 'blue'`) in a second valid POST is ignored (201, no `colour`
      key). A valid POST with a 120-character title returns 201.
- [ ] AC5 (URS-N-02) Malformed JSON and empty body are JSON 400, non-JSON content type is
      JSON 415, on `POST /api/tickets`: `$this->client->request('POST', '/api/tickets', server: ['CONTENT_TYPE' => 'application/json'], content: '{not json')`
      and the same with `content: ''` each return 400 with `Content-Type` starting with
      `application/json` and `error` a non-empty string;
      `$this->client->request('POST', '/api/tickets', server: ['CONTENT_TYPE' => 'text/plain'], content: '{"title":"T","description":"d","priority":"low"}')`
      returns 415 with the same JSON shape. `GET /api/tickets` then returns `count === 0`.
- [ ] AC6 (URS-N-02) Unknown ticket id on show is a JSON 404: `createTicket()` once (so the
      table is not empty), then `GET /api/tickets/9999` returns 404, `Content-Type` starting
      with `application/json`, body exactly `['error' => 'Ticket not found']`; `GET /api/tickets/0`
      returns the same; `GET /api/tickets/{existing id}` still returns 200 with the eight keys.
- [ ] AC7 (URS-N-02) Non-numeric id and wrong method on tickets are JSON, never 500:
      `GET /api/tickets/abc` returns 404 with `Content-Type` starting with `application/json`
      and `error` a non-empty string; `DELETE /api/tickets/1` (via `$this->client->request('DELETE', ...)`)
      returns 405 with the same JSON shape and an `Allow` header containing `GET`.
- [ ] AC8 (URS-N-02) Stats on an empty table: with no tickets `GET /api/stats` returns 200,
      `Content-Type` starting with `application/json`, `array_keys($body)` exactly
      `['total', 'open', 'in_progress', 'closed', 'closed_ratio']`, `assertSame(0, ...)` for
      `total`, `open`, `in_progress`, `closed`, and `assertEquals(0, $body['closed_ratio'])`.
- [ ] AC9 (URS-N-03) Stats with tickets unchanged: `createTicket(status: 'closed')`,
      `createTicket(status: 'open')`, `createTicket(status: 'in_progress')`, then
      `GET /api/stats` returns 200 with `total === 3`, `open === 1`, `in_progress === 1`,
      `closed === 1` and `assertEquals(33.3, $body['closed_ratio'])`.
- [ ] AC10 (section 6) Standard scenario replay through the test client produces no 500: on
      the fresh schema of `setUp()`, send the twelve requests of
      `src/Command/SimulateTrafficCommand.php:52-65` in order with the same methods, URIs and
      bodies (copy the array into the test; use `$this->client->request($method, $uri, server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], content: null === $body ? null : json_encode($body))`),
      collect `getStatusCode()` after each, and assert the sequence is exactly
      `[200, 201, 201, 422, 422, 200, 200, 404, 200, 422, 200, 200]`; additionally assert every
      response has `Content-Type` starting with `application/json` and `body()` decodes
      without throwing.
- [ ] AC11 (section 6) The console command reports no 500: build
      `new \Symfony\Bundle\FrameworkBundle\Console\Application(static::$kernel)` after
      `createClient()` has booted it, `setAutoExit(false)`, wrap `$application->find('app:simulate-traffic')`
      in a `\Symfony\Component\Console\Tester\CommandTester`, `execute(['--reset' => true])`,
      assert `getStatusCode() === 0`, and assert the display does not match `/-> 5\d\d$/m`
      and does not match `/^HTTP 5\d\d:/m`, matches `/^HTTP 422: 3$/m` and `/^HTTP 404: 1$/m`.
      (Runs against the per-process test database and `var/log/test.log`, which is fine; the
      verifier additionally runs `tools/php bin/console app:simulate-traffic --reset` in dev
      and greps `var/log/dev.log` for new `CRITICAL` lines, expecting none.)
- [ ] AC12 (URS-N-01) Audit log sweep: `$agent = createAgent()`, `$ticket = createTicket()`,
      `$this->client->disableReboot()`, push a `Monolog\Handler\TestHandler` on
      `static::getContainer()->get(\Psr\Log\LoggerInterface::class)`; then
      `patchJson("/api/tickets/{id}/assignee", ['agentId' => $agent->getId()])` (200),
      `patchJson("/api/tickets/{id}/assignee", ['agentId' => null])` (200),
      `patchJson("/api/agents/{agentId}", ['active' => false])` (200). Assert
      `hasInfoThatPasses` finds a record with message `Ticket assignee changed` and context
      exactly `['id' => $ticket->getId(), 'assignee' => $agent->getId()]`, another with
      context exactly `['id' => $ticket->getId(), 'assignee' => null]`, and one with message
      `Agent activation changed` and context exactly `['id' => $agent->getId(), 'active' => false]`;
      assert exactly two records carry the first message and exactly one the second.
- [ ] AC13 (URS-F-03.2, URS-N-03) Amend `tests/Api/F0SlaFoundationTest.php::testAc11UrsF0302UnknownPriorityFallsBackToNormalTarget`
      only: replace lines 212-214 (POST `urgent` -> 201, 86400 s) with `postJson('/api/tickets', ['title' => 'Urgent one', 'description' => 'd', 'priority' => 'urgent'])`
      returning 422 with `errors.priority` a non-empty string; keep lines 216-218
      (`Ticket::slaTargetHours('urgent') === 24`, `('') === 24`, `('LOW') === 24`) unchanged.
      No other line of that file and no other existing test file changes.
- [ ] AC14 (URS-N-03) Baseline and shipped suites unchanged: `tests/Api/TicketApiTest.php`
      is byte-identical to its current content (the tester does not edit it) and the full
      suite passes; as a sweep check inside the new class, after one valid `POST /api/tickets`,
      `GET /api/tickets`, `GET /api/tickets/{id}`, `PATCH /api/tickets/{id}/status` with
      `['status' => 'closed']` and `PATCH /api/tickets/{id}/assignee` with `['agentId' => null]`
      each return 200 with `array_keys` exactly the eight ticket keys, and
      `PATCH /api/tickets/{id}/status` with `['status' => 'done']` still returns 422 with body
      exactly `['error' => 'Invalid status', 'allowed' => ['open', 'in_progress', 'closed']]`.

## Out of scope

- Do not add `urgent` (or any value) to `Ticket::PRIORITIES`, `SLA_TARGET_HOURS` or
  `STATUSES`; do not change `Ticket::slaTargetHours()`, the constructor or `toArray()`
  (`src/Entity/Ticket.php` is not modified at all). No migration, no schema change, no
  cleanup of rows whose priority is not in the list. `requests/002-urgent-priority.md` stays
  in the inbox (see Required behaviour 4); `requests/003-dashboard-blank-on-monday.md` is
  resolved by Required behaviour 7 and may be moved to `done/` by the orchestrator.
- Do not touch `src/Controller/SlaReportController.php`, `src/Service/SlaReport.php` or
  `tests/Api/U5SlaReportTest.php` (U5, being delivered concurrently): do not read them for
  conventions, do not reuse or call them, do not describe them in the developer report.
  Nothing in this item depends on `GET /api/reports/sla`.
- Do not modify `src/Command/SimulateTrafficCommand.php` (the scenario, including the
  `urgent` POST, the POST without priority, `/9999` and `done`, is the acceptance fixture),
  `src/EventListener/ApiExceptionListener.php` (keep it limited to `HttpExceptionInterface`;
  no `\Throwable` catch, no `framework.exceptions` config, no error-renderer swap),
  `src/Controller/AgentController.php`, `src/Controller/StatsController.php`, `src/Dto/*`
  (other than the new `CreateTicketRequest`), `src/Repository/*`, `src/Entity/Agent.php`,
  `config/`, `composer.json`, `migrations/`.
- In `src/Controller/TicketController.php` change only `create()` (DTO binding) and `show()`
  (null check). `list()`, `changeStatus()` (keeps its `json_decode()` and exact bodies) and
  `assign()` are untouched.
- In `src/Service/TicketStats.php` change only the zero guard; keep the five keys, their
  order and the rounding. No `JSON_PRESERVE_ZERO_FRACTION` on `/api/stats`.
- Tests: `tests/Api/ApiTestCase.php` (shared with U5) and `tests/Api/TicketApiTest.php` are
  not edited; the only edit to an existing test is AC13. No new helpers in `ApiTestCase`.
- No logging requirement beyond what exists: no new log line for the 404 of `show()`, no
  change to the `Ticket created` line, no log for rejected creates.
- No `Location` header, no `description` trimming on store, no `title`/`description`
  uniqueness, no `PUT`/`DELETE` on tickets.

## Hints for the developer

- DTO (`src/Dto/CreateTicketRequest.php`), mirroring `CreateAgentRequest`:
  `final readonly class CreateTicketRequest { public function __construct(
  #[Assert\NotBlank(normalizer: 'trim')] #[Assert\Length(max: 120)] public string $title = '',
  #[Assert\NotBlank(normalizer: 'trim')] public string $description = '',
  #[Assert\NotBlank] #[Assert\Choice(choices: Ticket::PRIORITIES)] public string $priority = '',
  ) {} }`. Empty-string defaults make a *missing* key reach `NotBlank` (readable message, key
  present in `errors`); `null` or a wrong type (`123`) is collected by the Serializer on the
  same property path. `Ticket::PRIORITIES` is a constant expression, so it is valid inside
  the attribute. `ApiExceptionListener` keeps the first violation per path, so `''` on
  `priority` (which fails both `NotBlank` and `Choice`) still yields one message.
- Action: `public function create(#[MapRequestPayload(acceptFormat: 'json')] CreateTicketRequest $payload): JsonResponse`
  then `new Ticket($payload->title, $payload->description, $payload->priority)`, persist,
  flush, the existing `Ticket created` log line, `$this->json($ticket->toArray(), Response::HTTP_CREATED)`.
  `acceptFormat: 'json'` is what makes the 415 of AC5 deterministic; the empty body reaches
  the JSON decoder and is the 400. The `Request` import stays because `list()` uses it.
- `show()`: `if (null === $ticket) { return $this->json(['error' => 'Ticket not found'], Response::HTTP_NOT_FOUND); }`
  before `toArray()`, exactly like `changeStatus()`. Returning (not throwing
  `createNotFoundException`) keeps the body byte-exact and avoids an `ERROR` log line.
- `TicketStats::summary()`: `'closed_ratio' => 0 === $total ? 0.0 : round($closed / $total * 100, 1)`
  (or an early return of the all-zero array). `JsonResponse` encodes `0.0` as `0`, which is
  why AC8 compares the ratio with `assertEquals`.
- Replay in tests (AC10): the scenario array is a private local in `execute()`; copy the
  twelve entries verbatim into the test class (a `private const array SCENARIO`). Use the
  `KernelBrowser` directly rather than `postJson()` so `null` bodies and the
  `HTTP_ACCEPT` header match the command. With the schema reset by `setUp()` the first
  ticket gets id 1, as after `--reset`.
- AC11: `static::$kernel` is set by `createClient()`; `Symfony\Bundle\FrameworkBundle\Console\Application`
  registers the app commands from the container. The command's `--reset` recreates the test
  schema, which `setUp()` also does, so the test is self-contained. Its `kernel->handle(...,
  catch: true)` turns any exception into a 500 response that the output would show as
  `-> 500`, hence the regex assertions.
- AC12: same `TestHandler` technique as U3 AC18 (`$this->client->disableReboot()` first, then
  `pushHandler` on the container's `LoggerInterface`); all three handlers on the stack
  receive each record, `fingers_crossed` does not interfere. Count records with
  `array_filter($handler->getRecords(), fn (LogRecord $r) => 'Ticket assignee changed' === $r->message)`.
- After the change, run `tools/php bin/console app:simulate-traffic --reset` once and read the
  tail of `var/log/dev.log`: the only new exception lines are two `request.ERROR ...
  HttpException` entries for the two 422 POSTs; a `request.CRITICAL` line means a path is
  still unguarded.
