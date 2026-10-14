# U3: PATCH /api/tickets/{id}/assignee (assign, unassign) and assignee object in all ticket JSON

**Kind:** feature
**URS:** docs/urs/URS-002-sla-module.md
**Evidence:** URS-F-02.2, URS-F-02.3, URS-F-02.4
**Constraints:** URS-N-01, URS-N-02, URS-N-03 (docs/urs/URS-002-sla-module.md, section 5)
**Depends on:** F0 (specs/F0-sla-foundation.md, shipped)

## Current behaviour

There is no assignee endpoint. `TicketController` (`src/Controller/TicketController.php:17-86`,
class-level `#[Route('/api/tickets')]`) has four actions: `list` (lines 27-41, `status`
filter from R1), `create` (43-55), `show` (57-63) and `changeStatus` on
`PATCH /{id}/status` (65-85). `PATCH /api/tickets/{id}/assignee` therefore falls through to
the kernel's 404. The persistence side exists from F0: `Ticket::$assignee` is a nullable
`ManyToOne` to `Agent` (`src/Entity/Ticket.php:42-44`, join column `assignee_id`,
`onDelete: 'SET NULL'`) with `getAssignee(): ?Agent` (100-103) and
`setAssignee(?Agent $assignee): void` (105-108). `Ticket::toArray()` (111-123) emits the
eight keys `id, title, description, priority, status, createdAt, dueAt, assignee` and
hard-codes `'assignee' => null` (line 121), the F0 placeholder, so an assigned ticket still
renders `"assignee": null` on every endpoint. `Agent` (`src/Entity/Agent.php`) exposes
`getName()` (40-43), `isActive()` (50-53) and a four-key `toArray()` (`id, name, email,
active`, 61-69); `AgentRepository` (`src/Repository/AgentRepository.php:12-28`) adds only
`findActiveFirst()` and inherits `find()`. The existing error conventions in this
controller are `{"error": "Ticket not found"}` with 404 (line 70) and
`{"error": "Invalid status", "allowed": [...]}` with 422 (line 76); the status change is
logged with `$this->logger->info('Ticket status changed', ['id' => $id, 'status' => $status])`
(line 82) through the autowired `LoggerInterface` (constructor, lines 20-25).
`src/EventListener/ApiExceptionListener.php` (lines 18-40, delivered with U1) already turns
any `HttpExceptionInterface` raised under `/api/` into JSON: `{"errors": {propertyPath:
message}}` when the previous exception is a `ValidationFailedException` (lines 29-34),
otherwise `{"error": message}` (line 36), keeping the exception's status code. With
`#[MapRequestPayload]` (`symfony/serializer` and `symfony/validator` are installed,
`composer.json:19-20`) a denormalisation or validation failure is a 422 `HttpException`
wrapping a `ValidationFailedException`
(`vendor/symfony/http-kernel/Controller/ArgumentResolver/RequestPayloadValueResolver.php:134-179`),
invalid JSON is a 400 `BadRequestHttpException` (line 267) and a non-JSON content type a
415 (lines 239-243). Note on distinguishing a missing key from an explicit `null`: when a
constructor parameter is nullable and absent from the body, the Serializer silently passes
`null` (`vendor/symfony/serializer/Normalizer/AbstractNormalizer.php:397`) unless the
`REQUIRE_ALL_PROPERTIES` context flag (line 120) is set, in which case the missing
parameter is collected as an error on path `agentId` (lines 400-422, path built at 342).
The test base `tests/Api/ApiTestCase.php` offers `patchJson()` (line 45), `body()` (51),
`createAgent(name, email, active)` (56) and `createTicket(..., ?Agent $assignee = null,
string $status = 'open')` (66). In the test environment Monolog's `main` handler is
`fingers_crossed` at `action_level: error` (`config/packages/monolog.yaml:19-29`), so
`info` lines are not written to `var/log/test.log`; the tester captures them with a
`Monolog\Handler\TestHandler` pushed on the container's logger (see hints).

## Required behaviour

1. **Route (URS-F-02.2).** New action on `TicketController`: `PATCH /api/tickets/{id}/assignee`,
   `#[Route('/{id}/assignee', name: 'ticket_assignee', requirements: ['id' => '\d+'], methods: ['PATCH'])]`.
   The request body is a JSON object `{"agentId": int|null}` sent with
   `Content-Type: application/json`. Unknown keys in the body are ignored. The three
   existing routes, their names and requirements are unchanged.
2. **Order of checks.** Body binding (points 7-9: 415, 400, 422) happens before the action
   runs; then the ticket lookup (point 6); then, when `agentId` is not `null`, the agent
   lookup (point 4) and the active check (point 5). So an unknown ticket with an unknown
   agent is a 404 `Ticket not found`, and a malformed body on an unknown ticket is the
   body error, not 404.
3. **Assign (URS-F-02.2, URS-N-01).** `{"agentId": N}` where `N` is the id of an existing
   agent with `active === true`: the ticket's `assignee` is set to that agent and
   flushed; the response is **200**, `Content-Type: application/json`, body
   `Ticket::toArray()` with `"assignee": {"id": N, "name": "<agent name>"}`. One log line
   is written at level `info` through the controller's injected `LoggerInterface` with
   message exactly `Ticket assignee changed` and context exactly
   `['id' => <ticket id, int>, 'assignee' => N]` (ticket id and the new value, URS-N-01).
   Assigning an agent that is already the assignee is idempotent (200, same body, log
   line still written). Reassigning from agent A to agent B replaces A with B.
4. **Unknown agent (URS-F-02.2, URS-N-02).** `{"agentId": N}` where no agent has id `N`
   (including `0` and negative integers): **404** with body exactly
   `{"error": "Agent not found"}`. The ticket is unchanged (a subsequent
   `GET /api/tickets/{id}` shows the previous assignee or `null`) and no log line is
   written.
5. **Inactive agent (URS-F-02.2, URS-N-02).** `{"agentId": N}` where agent `N` exists with
   `active === false`: **422** with body exactly
   `{"errors": {"agentId": "Agent is not active."}}`. The ticket is unchanged: if it was
   assigned to agent A before, it is still assigned to A afterwards (both in the
   database and in `GET /api/tickets/{id}`); no flush of a changed assignee, no log line.
6. **Unknown ticket (URS-N-02).** `PATCH /api/tickets/{id}/assignee` with a well-formed
   body and an `{id}` that matches `\d+` but is no ticket: **404** with body exactly
   `{"error": "Ticket not found"}` (same text as `changeStatus`, line 70). A non-numeric
   `{id}` (for example `/api/tickets/abc/assignee`) does not match the route and is a
   **404** rendered as JSON `{"error": "<message>"}` by `ApiExceptionListener`; never
   HTML, never 500.
7. **Unassign (URS-F-02.3, URS-N-01).** `{"agentId": null}`: the ticket's `assignee` is
   set to `null` and flushed; response **200** with `Ticket::toArray()` where
   `"assignee": null`. A log line `Ticket assignee changed` with context
   `['id' => <ticket id>, 'assignee' => null]` is written, also when the ticket had no
   assignee (idempotent). No agent lookup takes place, so `null` never yields 404/422.
8. **Missing or mistyped `agentId` (URS-N-02).** A body without the `agentId` key (`{}`,
   or an object with only other keys) is **not** an unassignment: it returns **422**
   with `Content-Type: application/json` and body `{"errors": {"agentId": "<non-empty
   message>"}}`; the ticket is unchanged. The same 422 shape applies when `agentId` is
   present but not an integer or `null`, for example `"abc"`, `"5"` (a numeric string is
   not an integer), `true`, `1.5`. Exact message texts are not prescribed.
9. **Other client faults before the action (URS-N-02).** A body that is not valid JSON
   (for example `{not json`) or an empty body returns **400**; a body sent with a
   non-JSON `Content-Type` (for example `text/plain`) returns **415**. Both respond with
   `Content-Type: application/json` and `{"error": "<non-empty message>"}`. Never HTML,
   never 500. (Same convention as U1, rendered by the existing `ApiExceptionListener`.)
10. **Assignee object everywhere (URS-F-02.4, URS-N-03).** `Ticket::toArray()` keeps its
    eight keys in the existing order (`id, title, description, priority, status,
    createdAt, dueAt, assignee`) with unchanged values and formats, and the `assignee`
    value becomes: `null` when `getAssignee()` is `null`, otherwise exactly the two-key
    object `{"id": <agent id, int>, "name": <agent name, string>}` in that key order
    (not `Agent::toArray()`: no `email`, no `active`). Because every ticket endpoint
    serialises through `toArray()`, this applies to `GET /api/tickets` (each item),
    `POST /api/tickets` (always `null` on creation), `GET /api/tickets/{id}`,
    `PATCH /api/tickets/{id}/status` and the new `PATCH /api/tickets/{id}/assignee`. The
    object is emitted regardless of the agent's `active` flag (an agent deactivated after
    assignment stays visible as the assignee; deactivation does not unassign).
11. **Controller and DTO (AGENTS.md).** The action binds its body with
    `#[MapRequestPayload]` on a new `final readonly` DTO (`src/Dto/AssignTicketRequest.php`)
    using constructor property promotion; no `json_decode()` and no manual
    `SerializerInterface`/`ValidatorInterface` calls. The controller stays thin: look up
    the ticket (`TicketRepository`), look up the agent (`AgentRepository`, autowired into
    the constructor, or `EntityManagerInterface::find(Agent::class, ...)`), check
    `isActive()`, `setAssignee()`, flush, log, return `$this->json($ticket->toArray())`.
    No change to `AgentRepository`, `Agent`, `TicketRepository`, migrations or config.
12. **Existing endpoints unchanged (URS-N-03).** `GET/POST /api/tickets`,
    `GET /api/tickets/{id}`, `PATCH /api/tickets/{id}/status` and `GET /api/stats` keep
    their status codes, envelopes, keys and error bodies; the only visible difference is
    the `assignee` value of point 10 for tickets that have an assignee.

## Acceptance criteria

Each criterion is one test method in a new `tests/Api/U3TicketAssigneeTest.php` extending
`ApiTestCase` (use `createAgent()`, `createTicket()`, `patchJson()`, `body()`; read the
header with `$this->client->getResponse()->headers->get('Content-Type')`; reload a ticket
from the database with `$this->em()->clear()` followed by
`$this->em()->find(Ticket::class, $id)`). The existing `tests/Api/TicketApiTest.php`,
`tests/Api/F0SlaFoundationTest.php` and `tests/Api/R1FilterTicketsByStatusTest.php` must
stay green unchanged.

- [ ] AC1 (URS-F-02.2) Assign an active agent: `$agent = createAgent('Ada', 'ada@example.test')`,
      `$ticket = createTicket()`; `patchJson("/api/tickets/{$ticket->getId()}/assignee", ['agentId' => $agent->getId()])`
      returns 200, `Content-Type` starting with `application/json`, `array_keys($body)`
      exactly `['id', 'title', 'description', 'priority', 'status', 'createdAt', 'dueAt', 'assignee']`,
      and `$body['assignee']` exactly `['id' => $agent->getId(), 'name' => 'Ada']`
      (`array_keys` exactly `['id', 'name']`). After `em()->clear()`, the reloaded ticket's
      `getAssignee()->getId()` equals `$agent->getId()`.
- [ ] AC2 (URS-F-02.2) Reassign replaces the assignee: `$a = createAgent('Ada', 'ada@example.test')`,
      `$b = createAgent('Bob', 'bob@example.test')`, `$ticket = createTicket(assignee: $a)`;
      PATCH with `['agentId' => $b->getId()]` returns 200 with
      `assignee === ['id' => $b->getId(), 'name' => 'Bob']`; `GET /api/tickets/{id}` then
      returns the same `assignee`. A second identical PATCH (same agent again) also
      returns 200 with the same `assignee`.
- [ ] AC3 (URS-F-02.2) Unknown agent is a JSON 404 and leaves the ticket unchanged:
      `$a = createAgent()`, `$ticket = createTicket(assignee: $a)`; PATCH with
      `['agentId' => 9999]` returns 404, `Content-Type` starting with `application/json`,
      body exactly `['error' => 'Agent not found']`; `GET /api/tickets/{id}` still returns
      `assignee['id'] === $a->getId()`. The same request with `['agentId' => 0]` also
      returns 404 with that body.
- [ ] AC4 (URS-F-02.2) Inactive agent is a 422 validation error and leaves the ticket
      unchanged: `$a = createAgent('Ada', 'ada@example.test')`,
      `$inactive = createAgent('Zed', 'zed@example.test', false)`,
      `$ticket = createTicket(assignee: $a)`; PATCH with `['agentId' => $inactive->getId()]`
      returns 422, `Content-Type` starting with `application/json`, body exactly
      `['errors' => ['agentId' => 'Agent is not active.']]`; `GET /api/tickets/{id}`
      returns `assignee === ['id' => $a->getId(), 'name' => 'Ada']`, and after
      `em()->clear()` the reloaded ticket's `getAssignee()->getId()` is `$a->getId()`.
- [ ] AC5 (URS-F-02.2) Inactive agent on an unassigned ticket: `$inactive = createAgent('Zed', 'zed@example.test', false)`,
      `$ticket = createTicket()`; PATCH with `['agentId' => $inactive->getId()]` returns
      422 with the AC4 body, and `GET /api/tickets/{id}` returns `assignee === null`.
- [ ] AC6 (URS-N-02) Unknown ticket is a JSON 404: `$agent = createAgent()`; PATCH
      `/api/tickets/9999/assignee` with `['agentId' => $agent->getId()]` returns 404,
      `Content-Type` starting with `application/json`, body exactly
      `['error' => 'Ticket not found']`; the same with `['agentId' => null]` also returns
      404 with that body.
- [ ] AC7 (URS-N-02) Non-numeric ticket id is a JSON 404, not HTML and not 500:
      `patchJson('/api/tickets/abc/assignee', ['agentId' => null])` returns 404,
      `Content-Type` starting with `application/json`, and a body whose `error` key is a
      non-empty string (`body()` decodes without throwing).
- [ ] AC8 (URS-F-02.3) Unassign: `$agent = createAgent()`, `$ticket = createTicket(assignee: $agent)`;
      PATCH with `['agentId' => null]` returns 200 with `array_keys($body)` as in AC1 and
      `$body['assignee'] === null` (`array_key_exists('assignee', $body)` is true);
      `GET /api/tickets/{id}` returns `assignee === null`, and after `em()->clear()` the
      reloaded ticket's `getAssignee()` is `null`.
- [ ] AC9 (URS-F-02.3) Unassign is idempotent: `$ticket = createTicket()` (no assignee);
      PATCH with `['agentId' => null]` returns 200 with `assignee === null`.
- [ ] AC10 (URS-N-02) Missing `agentId` is a 422, not an unassignment:
      `$agent = createAgent()`, `$ticket = createTicket(assignee: $agent)`; send the raw
      content `{}` (see hints) and `patchJson(..., ['other' => 1])`; each returns 422,
      `Content-Type` starting with `application/json`, a body with exactly the top-level
      key `errors` whose `agentId` value is a non-empty string; `GET /api/tickets/{id}`
      still returns `assignee['id'] === $agent->getId()`.
- [ ] AC11 (URS-N-02) Mistyped `agentId` is a 422, never 500: `$ticket = createTicket()`;
      for each of `['agentId' => 'abc']`, `['agentId' => '5']`, `['agentId' => true]`,
      `['agentId' => 1.5]`, PATCH returns 422 with `Content-Type` starting with
      `application/json` and `errors.agentId` a non-empty string.
- [ ] AC12 (URS-N-02) Malformed JSON is a JSON 400, not HTML and not 500: `$ticket = createTicket()`;
      `$this->client->request('PATCH', "/api/tickets/{$ticket->getId()}/assignee", server: ['CONTENT_TYPE' => 'application/json'], content: '{not json')`
      returns 400 with `Content-Type` starting with `application/json` and a body whose
      `error` key is a non-empty string. The same request with `content: ''` (empty
      body) also returns 400 with the same shape.
- [ ] AC13 (URS-N-02) Unsupported content type is a JSON 415: `$ticket = createTicket()`;
      `$this->client->request('PATCH', "/api/tickets/{$ticket->getId()}/assignee", server: ['CONTENT_TYPE' => 'text/plain'], content: '{"agentId":null}')`
      returns 415 with `Content-Type` starting with `application/json` and a body whose
      `error` key is a non-empty string.
- [ ] AC14 (URS-F-02.4) List shows the assignee object: `$agent = createAgent('Ada', 'ada@example.test')`,
      `createTicket(title: 'Assigned', assignee: $agent, createdAt: new \DateTimeImmutable('-2 hours'))`,
      `createTicket(title: 'Free', createdAt: new \DateTimeImmutable('-1 hour'))`;
      `GET /api/tickets` returns 200, `count === 2`, `items[0]['title'] === 'Free'` with
      `items[0]['assignee'] === null`, `items[1]['title'] === 'Assigned'` with
      `items[1]['assignee'] === ['id' => $agent->getId(), 'name' => 'Ada']`; every item
      has `array_keys` exactly as in AC1.
- [ ] AC15 (URS-F-02.4) Show and status endpoints carry the assignee object:
      `$agent = createAgent('Ada', 'ada@example.test')`, `$ticket = createTicket(assignee: $agent)`;
      `GET /api/tickets/{id}` returns 200 with `assignee === ['id' => $agent->getId(), 'name' => 'Ada']`;
      `PATCH /api/tickets/{id}/status` with `['status' => 'closed']` returns 200 with
      `status === 'closed'` and the same `assignee` object.
- [ ] AC16 (URS-F-02.4) Create still yields `null`: `POST /api/tickets` with
      `['title' => 'New', 'description' => 'x', 'priority' => 'low']` returns 201 with
      `array_keys` as in AC1 and `assignee === null`.
- [ ] AC17 (URS-F-02.4) An inactive agent stays visible as assignee:
      `$agent = createAgent('Ada', 'ada@example.test')`, `$ticket = createTicket(assignee: $agent)`,
      then `$agent->setActive(false); $this->em()->flush();`; `GET /api/tickets/{id}`
      returns `assignee === ['id' => $agent->getId(), 'name' => 'Ada']`.
- [ ] AC18 (URS-N-01) Assignment is logged at info with ticket id and new value:
      `$agent = createAgent()`, `$ticket = createTicket()`; push a
      `Monolog\Handler\TestHandler` onto `static::getContainer()->get(\Psr\Log\LoggerInterface::class)`
      (see hints), PATCH with `['agentId' => $agent->getId()]` (200), then assert
      `$handler->hasInfoThatPasses(fn (\Monolog\LogRecord $r) => 'Ticket assignee changed' === $r->message && ['id' => $ticket->getId(), 'assignee' => $agent->getId()] === $r->context)`
      is true.
- [ ] AC19 (URS-N-01) Unassignment is logged: as AC18 but `$ticket = createTicket(assignee: $agent)`
      and PATCH with `['agentId' => null]`; assert a record with message
      `Ticket assignee changed` and context exactly `['id' => $ticket->getId(), 'assignee' => null]`.
- [ ] AC20 (URS-N-01) Rejected requests are not logged: `$inactive = createAgent('Zed', 'zed@example.test', false)`,
      `$ticket = createTicket()`; with the TestHandler attached, PATCH with
      `['agentId' => $inactive->getId()]` (422) and then with `['agentId' => 9999]` (404)
      (call `$this->client->disableReboot()` before the first request); assert
      `$handler->hasInfoThatContains('Ticket assignee changed')` is false.
- [ ] AC21 (URS-N-03) Existing endpoints keep their responses: `PATCH /api/tickets/{id}/status`
      with `['status' => 'done']` still returns 422 with body exactly
      `['error' => 'Invalid status', 'allowed' => ['open', 'in_progress', 'closed']]`;
      `GET /api/tickets?status=bogus` still returns 400 with that same body;
      `PATCH /api/tickets/9999/status` with `['status' => 'closed']` still returns 404
      `['error' => 'Ticket not found']`; `GET /api/stats` still returns 200 with exactly
      the keys `total, open, in_progress, closed, closed_ratio`.

## Out of scope

- No `GET /api/tickets?assignee=` filter, no `breached` filter (URS-F-04), no SLA report
  (URS-F-05), no `PATCH /api/agents/{id}` (URS-F-01.4) and no agent activation logging
  (that half of URS-N-01 ships with URS-F-01.4). Deactivating an agent does not unassign
  its tickets.
- Do not touch `src/Controller/AgentController.php`, `src/Dto/CreateAgentRequest.php`,
  `src/EventListener/ApiExceptionListener.php` (reuse it as is; if it is absent when you
  start, create it exactly as specs/U1-agents-create-list.md Required behaviour 8 and its
  hints describe, nothing more), `src/Repository/AgentRepository.php`,
  `src/Repository/TicketRepository.php`, `src/Entity/Agent.php` (the two-key object is
  built in `Ticket::toArray()`, not via a new `Agent` method), `src/Service/TicketStats.php`,
  `src/Command/SimulateTrafficCommand.php`, `migrations/`, `config/`, `composer.json`,
  `tests/Api/ApiTestCase.php`, `tests/Api/TicketApiTest.php`,
  `tests/Api/F0SlaFoundationTest.php`, `tests/Api/R1FilterTicketsByStatusTest.php`.
  No new migration (no schema change: `assignee_id` exists since F0).
- In `src/Entity/Ticket.php` change only the `assignee` value inside `toArray()`; no other
  key, order, format or method changes. In `src/Controller/TicketController.php` add the
  constructor dependency and the new action only: `list`, `create`, `show` and
  `changeStatus` keep their bodies, status codes and the `json_decode()` they already use
  (the pre-existing 500 on `GET /api/tickets/{unknown id}` is not this item's concern).
- Do not add `email` or `active` to the assignee object, no `Location` header, no
  response body change on `GET /api/stats`.
- Do not catch arbitrary `\Throwable`, do not configure `framework.exceptions`, do not
  swap the error renderer project-wide.

## Hints for the developer

- DTO: `final readonly class AssignTicketRequest { public function __construct(public ?int $agentId) {} }`
  in `src/Dto/`, deliberately **without** a default value. Bind it with
  `#[MapRequestPayload(acceptFormat: 'json', serializationContext: [AbstractNormalizer::REQUIRE_ALL_PROPERTIES => true])] AssignTicketRequest $payload`
  (`Symfony\Component\Serializer\Normalizer\AbstractNormalizer`). With that flag a body
  without `agentId` is collected as a `NotNormalizableValueException` on path `agentId`
  (`AbstractNormalizer.php:397-422`), the `ArgumentCountError` from the constructor is a
  `TypeError` that the Serializer swallows (lines 451-474), and the resolver turns the
  collected error into the 422 `{"errors": {"agentId": "..."}}` via `ApiExceptionListener`;
  without the flag `{}` would silently become `agentId = null` and unassign (point 8).
  `"abc"`, `"5"`, `true`, `1.5` fail the `?int` type check in the JSON format (no scalar
  coercion outside xml/csv) and land on the same path. `acceptFormat: 'json'` makes AC13
  a deterministic 415; an empty body reaches the JSON decoder and is the 400 of AC12.
- Controller: add `private readonly AgentRepository $agents` to the constructor
  (autowired). Action sketch: find the ticket or return
  `$this->json(['error' => 'Ticket not found'], 404)`; `$agent = null;` if
  `$payload->agentId` is not `null`, `$agent = $this->agents->find($payload->agentId)`,
  return 404 `['error' => 'Agent not found']` when it is `null`, return 422
  `['errors' => ['agentId' => 'Agent is not active.']]` when `!$agent->isActive()`; then
  `$ticket->setAssignee($agent); $this->em->flush();
  $this->logger->info('Ticket assignee changed', ['id' => $id, 'assignee' => $payload->agentId]);
  return $this->json($ticket->toArray());`. Returning the 404/422 from the action (rather
  than throwing) keeps the bodies byte-exact and matches `changeStatus`.
- `Ticket::toArray()`: `'assignee' => null === $this->assignee ? null : ['id' => $this->assignee->getId(), 'name' => $this->assignee->getName()]`.
  Doctrine proxies resolve `getName()` lazily, so the list endpoint needs no join change.
- Log context must be exactly two keys, `id` (int, the route `$id`) then `assignee`
  (int or `null`), so the tester can compare with `===`.
- Tests for the log (AC18-AC20): `Psr\Log\LoggerInterface` is the alias of `monolog.logger`
  (the same instance the controller receives), reachable through the test container:
  ```php
  $this->client->disableReboot(); // keep the same container across requests in one test
  $logger = static::getContainer()->get(\Psr\Log\LoggerInterface::class);
  $handler = new \Monolog\Handler\TestHandler();
  $logger->pushHandler($handler);
  ```
  then make the request and use `$handler->hasInfoThatPasses(...)` /
  `hasInfoThatContains(...)`. The `fingers_crossed` handler does not interfere: every
  handler on the stack receives the record.
- Tests for AC10: `patchJson()` encodes `[]` as a JSON array `[]`, not an object, so send
  the literal `{}` with
  `$this->client->request('PATCH', $uri, server: ['CONTENT_TYPE' => 'application/json'], content: '{}')`;
  `patchJson($uri, ['other' => 1])` covers the second variant. Both must be 422 with
  `errors.agentId`.
