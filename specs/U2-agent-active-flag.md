# U2: PATCH /api/agents/{id} to change the active flag

**Kind:** feature
**URS:** docs/urs/URS-002-sla-module.md
**Evidence:** URS-F-01.4
**Constraints:** URS-N-01, URS-N-02 (docs/urs/URS-002-sla-module.md, section 5)
**Depends on:** F0 (specs/F0-sla-foundation.md, shipped), U1 (specs/U1-agents-create-list.md, shipped)

## Current behaviour

`src/Controller/AgentController.php` (class route `/api/agents`, line 19) has exactly two
actions: `list` (`GET ''`, lines 31-36) and `create` (`POST ''`, lines 38-58). There is no
route with an `{id}` segment, so `PATCH /api/agents/1` falls through to the router's
`NotFoundHttpException`, which `src/EventListener/ApiExceptionListener.php` (lines 20-40)
already renders as a JSON 404 `{"error": "No route found for ..."}` because it handles every
`HttpExceptionInterface` thrown under `/api/`. The entity side needs nothing: `App\Entity\Agent`
(`src/Entity/Agent.php`) has `isActive()` (lines 50-53), `setActive(bool)` (lines 55-58) and
`toArray()` returning exactly `{"id", "name", "email", "active"}` (lines 61-69). After
creation the flag can therefore only be changed in PHP (`tests/Api/ApiTestCase.php:56-64`
`createAgent(..., bool $active)`), never through HTTP. The controller already receives the
autowired `LoggerInterface` (constructor, lines 24-29) and uses it once, `'Agent created'`
(line 56); nothing logs an activation change, so URS-N-01 is unmet for agents. The
established error conventions come from U1: `#[MapRequestPayload(acceptFormat: 'json')]`
on a `final readonly` DTO with `#[Assert\...]` on promoted properties
(`src/Dto/CreateAgentRequest.php:13-26`), and `ApiExceptionListener` turning the resolver's
`HttpException` whose previous is a `ValidationFailedException` into
`{"errors": {<propertyPath>: <first message>}}` (lines 28-35) and any other
`HttpExceptionInterface` into `{"error": <message>}` (line 37), with the exception's status
code and headers (line 39). The resolver gives 400 for a body that is not valid JSON or is
empty (`vendor/symfony/http-kernel/Controller/ArgumentResolver/RequestPayloadValueResolver.php:267`)
and 415 for a non-JSON content type (lines 242-243); for JSON it deserializes without
`filter_bool` (line 263), so the ObjectNormalizer's strict `is_bool()` check
(`vendor/symfony/serializer/Normalizer/AbstractObjectNormalizer.php:715`) rejects `1`,
`"true"` and `"yes"` for a `bool` property, collecting the error on the property path
(`COLLECT_DENORMALIZATION_ERRORS`, proven by U1 AC7). A missing key for a constructor
parameter with a default becomes that default
(`vendor/symfony/serializer/Normalizer/AbstractNormalizer.php:395-396`), so a
`#[Assert\NotNull]` on it yields a violation at path `active`. The sibling ticket endpoint
`PATCH /api/tickets/{id}/status` (`src/Controller/TicketController.php:65-85`) is the
project's pattern for a PATCH: `requirements: ['id' => '\d+']`, `find($id)`, a returned
`{"error": "Ticket not found"}` 404 (lines 69-71), flush, then
`$this->logger->info('Ticket status changed', ['id' => $id, 'status' => $status])` (line 82).
In the test environment Monolog's `main` handler is `fingers_crossed` at
`action_level: error` (`config/packages/monolog.yaml:18-29`), so `info` lines never reach
`var/log/test.log`; the tester captures them with a `Monolog\Handler\TestHandler` pushed on
the container's logger, the same recipe `specs/U3-ticket-assignee.md` AC18-AC20 uses.
`tests/Api/ApiTestCase.php` offers `patchJson()` (line 45), `body()` (line 51),
`createAgent()` (line 56) and `createTicket(..., ?Agent $assignee = null, ...)` (line 66).
`symfony/property-access` and `symfony/property-info` are installed (`composer.json:18-19`),
as are `symfony/serializer` and `symfony/validator` (lines 21-22): nothing to install.

## Required behaviour

1. **Route (URS-F-01.4).** New action on `AgentController`:
   `#[Route('/{id}', name: 'agent_active', requirements: ['id' => '\d+'], methods: ['PATCH'])]`.
   The request body is a JSON object `{"active": bool}` sent with
   `Content-Type: application/json`, bound with `#[MapRequestPayload(acceptFormat: 'json')]`
   on a new `final readonly` DTO (`src/Dto/UpdateAgentActiveRequest.php`) whose single
   promoted property is `active`. No other route is added (in particular no
   `GET /api/agents/{id}`): a `GET` on `/api/agents/{id}` is a 405.
2. **Happy path (URS-F-01.4).** When the id matches an existing agent and `active` is a JSON
   boolean, the agent's flag is set to that value and flushed; the response is **200** with
   `Content-Type: application/json` and the body exactly `Agent::toArray()` after the change:
   `{"id": int, "name": string, "email": string, "active": bool}` (keys in that order).
   `name` and `email` are never modified by this endpoint. Keys other than `active` in the
   body are ignored (`ALLOW_EXTRA_ATTRIBUTES` default), including `name`, `email` and `id`.
   The change is persisted: a subsequent `GET /api/agents` shows the new value and the
   U1 ordering (active first, then id ascending) reflects it.
3. **No-op (URS-F-01.4).** Sending the value the agent already has is accepted: **200**, same
   body as point 2, no error. It is still logged (point 6).
4. **Validation (URS-N-02).** A body without a JSON boolean `active` is a client fault:
   **422** with `Content-Type: application/json` and the body
   `{"errors": {"active": "<non-empty message>"}}`, `errors` having `active` as its only key.
   Cases: key `active` missing (`{}`, `{"other": 1}`), `null`, a string (`"yes"`, `"true"`,
   `""`), a number (`1`, `0`), an array. The agent is unchanged (same `active` on
   `GET /api/agents`). Message text is not prescribed.
5. **Unknown id (URS-N-02).** A numeric id that matches no agent returns **404** with
   `Content-Type: application/json` and the body exactly `{"error": "Agent not found"}`.
   Payload mapping runs before the action, so an unknown id combined with an invalid body
   returns the 422 of point 4, not the 404.
6. **Audit log (URS-N-01).** Every successful PATCH (every 200, including the no-op of point
   3) writes exactly one record through the controller's injected `LoggerInterface` at
   level `info`, message exactly `Agent activation changed`, context exactly
   `['id' => <agent id, int>, 'active' => <new value, bool>]`, keys in that order and
   nothing else, so the tester can compare with `===`. No record with that message is
   written for a 400/404/415/422 response.
7. **Other client faults before the action runs (URS-N-02).** All JSON, never HTML, never
   500: a non-numeric id (`PATCH /api/agents/abc`) does not match the route and returns
   **404** `{"error": "<non-empty>"}`; `GET /api/agents/{id}` returns **405**
   `{"error": "<non-empty>"}` with an `Allow` header containing `PATCH`; a body that is
   not valid JSON (`{not json`) or an empty body returns **400** `{"error": "<non-empty>"}`;
   a non-JSON `Content-Type` (`text/plain`) returns **415** `{"error": "<non-empty>"}`.
   These all come from the existing `ApiExceptionListener`; nothing new is needed for them
   beyond the route requirement and `acceptFormat: 'json'`.
8. **Controller stays thin (AGENTS.md).** No `json_decode()`, no manual
   `SerializerInterface`/`ValidatorInterface` calls: `find($id)`, 404 if null,
   `setActive($payload->active)`, flush, log, `return $this->json($agent->toArray())`.
9. **Existing endpoints are unchanged.** `GET /api/agents`, `POST /api/agents` (including
   its 422 bodies and the duplicate-email message), `GET/POST /api/tickets`,
   `GET /api/tickets/{id}`, `PATCH /api/tickets/{id}/status` and `GET /api/stats` keep
   their status codes and JSON bodies. `ApiExceptionListener` keeps handling only
   `HttpExceptionInterface`.

## Acceptance criteria

Each criterion is one test method in a new `tests/Api/U2AgentActiveFlagTest.php` extending
`ApiTestCase` (use `createAgent()` for set-up, `patchJson()` and `body()` for JSON cases,
`$this->client->request()` for raw content; read the header with
`$this->client->getResponse()->headers->get('Content-Type')`). The existing
`tests/Api/TicketApiTest.php`, `tests/Api/F0SlaFoundationTest.php`,
`tests/Api/R1FilterTicketsByStatusTest.php` and `tests/Api/U1AgentsCreateListTest.php` must
stay green unchanged. `{id}` below is `$agent->getId()` of the agent created in the test.

- [ ] AC1 (URS-F-01.4) Deactivate: `$agent = createAgent('Ada', 'ada@example.test', true)`;
      `patchJson('/api/agents/{id}', ['active' => false])` returns 200, `Content-Type`
      starting with `application/json`, and the body exactly
      `['id' => $agent->getId(), 'name' => 'Ada', 'email' => 'ada@example.test', 'active' => false]`
      (`array_keys` exactly `['id', 'name', 'email', 'active']`).
- [ ] AC2 (URS-F-01.4) Reactivate: `$agent = createAgent('Zed', 'zed@example.test', false)`;
      `patchJson('/api/agents/{id}', ['active' => true])` returns 200 with `active === true`,
      `name === 'Zed'`, `email === 'zed@example.test'`.
- [ ] AC3 (URS-F-01.4) Persisted and reflected in the list order: `createAgent('Ann', 'ann@example.test', true)`
      then `createAgent('Bea', 'bea@example.test', true)`; PATCH Ann with `['active' => false]`
      (200); `GET /api/agents` returns 200, `count === 2`, `array_column($items, 'name')`
      exactly `['Bea', 'Ann']` and `array_column($items, 'active')` exactly `[true, false]`.
- [ ] AC4 (URS-F-01.4) No-op and ignored keys: `$agent = createAgent('Ada', 'ada@example.test', true)`;
      `patchJson('/api/agents/{id}', ['active' => true, 'name' => 'Mallory', 'email' => 'm@example.test', 'id' => 9999])`
      returns 200 and the body exactly
      `['id' => $agent->getId(), 'name' => 'Ada', 'email' => 'ada@example.test', 'active' => true]`;
      `GET /api/agents` then returns `count === 1` with `items[0]['name'] === 'Ada'`.
- [ ] AC5 (URS-N-02) Missing `active` is a JSON 422: `$agent = createAgent()` (active);
      `$this->client->request('PATCH', '/api/agents/{id}', server: ['CONTENT_TYPE' => 'application/json'], content: '{}')`
      returns 422, `Content-Type` starting with `application/json`, body with exactly the
      top-level key `errors`, `errors` with exactly the key `active`, whose value is a
      non-empty string; `patchJson('/api/agents/{id}', ['other' => 1])` gives the same
      shape; `GET /api/agents` then returns `items[0]['active'] === true`.
- [ ] AC6 (URS-N-02) Non-boolean `active` is a JSON 422, never 500: `$agent = createAgent()`;
      for each body `['active' => null]`, `['active' => 'yes']`, `['active' => 'true']`,
      `['active' => '']`, `['active' => 1]`, `['active' => 0]`, `['active' => []]`,
      `patchJson('/api/agents/{id}', ...)` returns 422 with `Content-Type` starting with
      `application/json` and `errors` having exactly the key `active` with a non-empty
      string value (`body()` must decode without throwing); `GET /api/agents` then returns
      `items[0]['active'] === true`.
- [ ] AC7 (URS-N-02) Unknown id: with no agents, `patchJson('/api/agents/9999', ['active' => false])`
      returns 404, `Content-Type` starting with `application/json`, and the body exactly
      `['error' => 'Agent not found']`.
- [ ] AC8 (URS-N-02) Validation precedes the lookup: with no agents,
      `patchJson('/api/agents/9999', ['active' => 'yes'])` returns 422 with `errors.active`
      a non-empty string (not 404).
- [ ] AC9 (URS-N-02) Non-numeric id is a JSON 404, not 500:
      `patchJson('/api/agents/abc', ['active' => false])` returns 404, `Content-Type`
      starting with `application/json`, and a body whose `error` key is a non-empty string.
- [ ] AC10 (URS-N-02) Wrong method is a JSON 405: `$agent = createAgent()`;
      `$this->client->request('GET', '/api/agents/{id}')` returns 405, `Content-Type`
      starting with `application/json`, a body whose `error` key is a non-empty string, and
      an `Allow` header containing `PATCH`.
- [ ] AC11 (URS-N-02) Malformed and empty bodies are JSON 400: `$agent = createAgent()`;
      `$this->client->request('PATCH', '/api/agents/{id}', server: ['CONTENT_TYPE' => 'application/json'], content: '{not json')`
      returns 400 with `Content-Type` starting with `application/json` and a body whose
      `error` key is a non-empty string; the same request with `content: ''` also returns
      400 with the same shape; `GET /api/agents` then returns `items[0]['active'] === true`.
- [ ] AC12 (URS-N-02) Unsupported content type is a JSON 415: `$agent = createAgent()`;
      `$this->client->request('PATCH', '/api/agents/{id}', server: ['CONTENT_TYPE' => 'text/plain'], content: '{"active":false}')`
      returns 415 with `Content-Type` starting with `application/json` and a body whose
      `error` key is a non-empty string; `GET /api/agents` then returns
      `items[0]['active'] === true`.
- [ ] AC13 (URS-N-01) Deactivation is logged at info with agent id and new value:
      `$agent = createAgent()`; push a `Monolog\Handler\TestHandler` onto
      `static::getContainer()->get(\Psr\Log\LoggerInterface::class)` (see hints), then
      `patchJson('/api/agents/{id}', ['active' => false])` (200); assert
      `$handler->hasInfoThatPasses(fn (\Monolog\LogRecord $r) => 'Agent activation changed' === $r->message && ['id' => $agent->getId(), 'active' => false] === $r->context)`
      is true, and that exactly one record of `$handler->getRecords()` has that message.
- [ ] AC14 (URS-N-01) Activation is logged: as AC13 but
      `$agent = createAgent('Zed', 'zed@example.test', false)` and
      `patchJson('/api/agents/{id}', ['active' => true])`; assert a record with message
      `Agent activation changed` and context exactly `['id' => $agent->getId(), 'active' => true]`.
- [ ] AC15 (URS-N-01) A no-op is logged with the unchanged value: as AC13 but
      `patchJson('/api/agents/{id}', ['active' => true])` on an active agent (200); assert a
      record with message `Agent activation changed` and context exactly
      `['id' => $agent->getId(), 'active' => true]`.
- [ ] AC16 (URS-N-01) Rejected requests are not logged: `$agent = createAgent()`; call
      `$this->client->disableReboot()` before the first request, attach the TestHandler,
      then `patchJson('/api/agents/9999', ['active' => false])` (404),
      `patchJson('/api/agents/{id}', ['active' => 'yes'])` (422) and
      `$this->client->request('PATCH', '/api/agents/{id}', server: ['CONTENT_TYPE' => 'text/plain'], content: '{"active":false}')`
      (415); assert `$handler->hasInfoThatContains('Agent activation changed')` is false.
- [ ] AC17 Existing endpoints keep their responses: `POST /api/agents` with
      `['name' => 'Ada', 'email' => 'ada@example.test']` still returns 201 with
      `array_keys` exactly `['id', 'name', 'email', 'active']`; a second identical POST still
      returns 422 with body exactly `['errors' => ['email' => 'An agent with this email already exists.']]`;
      `POST /api/agents` with the literal body `{}` still returns 422 with both
      `errors.name` and `errors.email`; `POST /api/tickets` with
      `['title' => 'T', 'description' => 'd', 'priority' => 'low']` still returns 201 with
      keys `id, title, description, priority, status, createdAt, dueAt, assignee`;
      `PATCH /api/tickets/{ticket id}/status` with `['status' => 'done']` still returns 422
      with `error === 'Invalid status'`; `GET /api/stats` still returns 200 with exactly the
      keys `total, open, in_progress, closed, closed_ratio`.
- [ ] AC18 (URS-F-01.4) Deactivation does not unassign tickets (service-level, same class):
      `$agent = createAgent()`, `$ticket = createTicket(assignee: $agent)`;
      `patchJson('/api/agents/{id}', ['active' => false])` (200); then
      `$this->em()->clear()`, `$reloaded = $this->em()->find(\App\Entity\Ticket::class, $ticket->getId())`;
      assert `$reloaded->getAssignee()?->getId() === $agent->getId()` and
      `$reloaded->getAssignee()->isActive() === false`.

## Out of scope

- No `GET /api/agents/{id}`, no `DELETE`, no `PUT`, no editing of `name` or `email` through
  this endpoint (extra keys are ignored, not rejected, and not applied), no filtering or
  pagination on the list, no assignment endpoints (URS-F-02), no breach filter
  (URS-F-04), no SLA report (URS-F-05).
- Deactivating an agent does not unassign or otherwise touch its tickets (AC18); no cascade,
  no second log line per ticket.
- Do not modify `src/Entity/Agent.php` (it already has `setActive()` and `toArray()`),
  `src/Dto/CreateAgentRequest.php`, `src/EventListener/ApiExceptionListener.php` (reuse as
  is), `src/Repository/AgentRepository.php`, `src/Service/TicketStats.php`,
  `src/Command/SimulateTrafficCommand.php`, `migrations/`, `config/`, `composer.json`,
  `tests/Api/ApiTestCase.php`, `tests/Api/TicketApiTest.php`,
  `tests/Api/F0SlaFoundationTest.php`, `tests/Api/R1FilterTicketsByStatusTest.php`,
  `tests/Api/U1AgentsCreateListTest.php`. No new migration (no schema change).
- Do not touch `src/Controller/TicketController.php`, `src/Repository/TicketRepository.php`
  or `src/Entity/Ticket.php` at all: U3 (`specs/U3-ticket-assignee.md`) is in flight on
  those files. This item changes exactly one existing file (`AgentController.php`: one new
  action, existing `list` and `create` untouched, constructor unchanged) and adds two new
  files (the DTO and the test class).
- Do not change the messages, keys or status codes of `list`/`create`; do not change how
  existing endpoints report errors; do not fix the pre-existing 500 on
  `GET /api/tickets/{unknown id}`. Do not catch arbitrary `\Throwable`, do not configure
  `framework.exceptions`, do not swap the error renderer project-wide.
- Do not add a `Location` header or any key beyond `Agent::toArray()` to the response. Do
  not log on failed requests, and do not add any logger channel or Monolog configuration.

## Hints for the developer

- `symfony/property-access` and `symfony/property-info` are already required
  (`composer.json:18-19`), so `#[MapRequestPayload]` works out of the box; nothing to
  install.
- DTO, in `src/Dto/UpdateAgentActiveRequest.php`:
  `final readonly class UpdateAgentActiveRequest { public function __construct(#[Assert\NotNull] public ?bool $active = null) {} }`.
  The `null` default makes a *missing* key reach `NotNull` (readable message, key `active`
  present in `errors`) instead of a constructor-argument error; an explicit `null` hits the
  same constraint; a wrong type (`"yes"`, `1`, `[]`) is collected by the Serializer
  (`COLLECT_DENORMALIZATION_ERRORS` is on in the resolver, strict `is_bool()` for the JSON
  format at `AbstractObjectNormalizer.php:715`) and surfaces as a violation on the same
  property path, so every AC5/AC6 case lands in the same 422 path through
  `ApiExceptionListener`. Do not use `bool $active = true`: a missing key would then
  silently activate the agent.
- Action signature:
  `public function changeActive(int $id, #[MapRequestPayload(acceptFormat: 'json')] UpdateAgentActiveRequest $payload): JsonResponse`
  with `#[Route('/{id}', name: 'agent_active', requirements: ['id' => '\d+'], methods: ['PATCH'])]`.
  The `\d+` requirement is what turns `abc` into the router's 404 (AC9) instead of a
  `TypeError` 500 when the kernel passes a non-numeric string to `int $id`.
  `acceptFormat: 'json'` is what makes AC12 a deterministic 415.
- Body: `$agent = $this->agents->find($id); if (null === $agent) { return $this->json(['error' => 'Agent not found'], Response::HTTP_NOT_FOUND); }`
  mirrors `TicketController::changeStatus` (lines 69-71) and keeps the body byte-exact
  (`throw $this->createNotFoundException('Agent not found')` produces the same JSON through
  the listener and is also acceptable). Then
  `$agent->setActive($payload->active); $this->em->flush();
  $this->logger->info('Agent activation changed', ['id' => $id, 'active' => $payload->active]);
  return $this->json($agent->toArray());`. Log after the flush so a failed flush is not
  recorded as a change; context must be exactly the two keys `id` then `active`.
- Tests for the log (AC13-AC16): `Psr\Log\LoggerInterface` is the alias of `monolog.logger`
  (the same instance the controller receives), reachable through the test container. The
  `KernelBrowser` reboots the kernel (new container, handler lost) before every request
  after the first (`vendor/symfony/framework-bundle/KernelBrowser.php:174-178`), so either
  make the PATCH the only HTTP request of the test (set-up via `createAgent()`, not via
  `POST /api/agents`) or call `$this->client->disableReboot()` first:
  ```php
  $this->client->disableReboot(); // keep the same container across requests in one test
  $logger = static::getContainer()->get(\Psr\Log\LoggerInterface::class);
  $handler = new \Monolog\Handler\TestHandler();
  $logger->pushHandler($handler);
  ```
  then make the request and use `$handler->hasInfoThatPasses(...)`,
  `hasInfoThatContains(...)` and `getRecords()`. The `fingers_crossed` handler does not
  interfere: every handler on the stack receives the record.
- Tests for AC5: `patchJson($uri, [])` encodes `[]` as a JSON array, not an object, so send
  the literal `{}` with
  `$this->client->request('PATCH', $uri, server: ['CONTENT_TYPE' => 'application/json'], content: '{}')`;
  `patchJson($uri, ['other' => 1])` covers the second variant. For AC9-AC12 call
  `$this->client->request()` directly as shown so the method, raw content and content type
  are under the test's control; read `Allow` with
  `$this->client->getResponse()->headers->get('Allow')`.
- AC18 needs `createTicket(assignee: $agent)` from `ApiTestCase` (line 66) and nothing
  from U3: it checks the relation through the entity manager, not through ticket JSON
  (whose `assignee` key U3 is changing).
