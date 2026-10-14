# U1: POST /api/agents (create, unique email) and GET /api/agents (list, active first)

**Kind:** feature
**URS:** docs/urs/URS-002-sla-module.md
**Evidence:** URS-F-01.2, URS-F-01.3
**Constraints:** URS-N-02 (docs/urs/URS-002-sla-module.md, section 5)
**Depends on:** F0 (specs/F0-sla-foundation.md, shipped)

## Current behaviour

There is no agent endpoint. `src/Controller/` contains only `TicketController.php`
(routes under `/api/tickets`) and `StatsController.php` (`/api/stats`,
`src/Controller/StatsController.php:14`); `GET /api/agents` and `POST /api/agents` therefore
fall through to the kernel's 404. The persistence side exists from F0: `App\Entity\Agent`
(`src/Entity/Agent.php:12-70`) has `name` (`length: 120`, line 19), `email` (`length: 180,
unique: true`, line 22), `active` (`bool`, default `true`, line 26), the constructor
`__construct(string $name, string $email, bool $active = true)` (lines 28-33) and
`toArray()` returning exactly `{"id", "name", "email", "active"}` (lines 61-69).
`App\Repository\AgentRepository` (`src/Repository/AgentRepository.php:12-18`) has no custom
query method, so there is no "active first" ordering anywhere. The unique index is enforced
only by the database: persisting a second agent with the same email throws
`Doctrine\DBAL\Exception\UniqueConstraintViolationException` on flush
(`tests/Api/F0SlaFoundationTest.php:138-144`), which, reached through HTTP, would be a 500.
There is no `src/Dto/` directory, no `#[Assert\...]` anywhere in `src/`, and no
`src/EventListener/`. `symfony/serializer` and `symfony/validator` are installed
(`composer.json:19-20`), so `#[MapRequestPayload]` is available; its default
`validationFailedStatusCode` is 422 (`vendor/symfony/http-kernel/Attribute/MapRequestPayload.php:45`).
Note for URS-N-02: when `#[MapRequestPayload]` fails it throws an `HttpException`
(`vendor/symfony/http-kernel/Controller/ArgumentResolver/RequestPayloadValueResolver.php:178`,
previous `ValidationFailedException`; line 267 gives a 400 `BadRequestHttpException` for a
body that is not valid JSON; lines 238-243 give a 415 for a non-JSON content type). The
framework renders those through `SerializerErrorRenderer`
(`vendor/symfony/framework-bundle/Resources/config/serializer.php:213-220`), whose format
comes from `Request::getPreferredFormat()` (`vendor/symfony/http-foundation/Request.php:1670-1686`),
i.e. the `Accept` header. The test client's default `Accept` is
`text/html,...` (`vendor/symfony/http-foundation/Request.php:377`), so without extra work
every validation failure would be an **HTML** 422 page, which URS-N-02 forbids.
The test base `tests/Api/ApiTestCase.php` already offers `postJson()` (line 39), `body()`
(line 51) and `createAgent(name, email, active)` (line 56). The list envelope used by the
existing API is `{"items": [...], "count": n}` (`tests/Api/TicketApiTest.php:27-28`,
`tests/Api/F0SlaFoundationTest.php:299-304`).

## Required behaviour

1. **`POST /api/agents`, happy path (URS-F-01.3).** Request body is a JSON object
   `{"name": string, "email": string, "active"?: bool}` sent with
   `Content-Type: application/json`. On success the response is **201** with
   `Content-Type: application/json` and the body is exactly `Agent::toArray()`:
   `{"id": int, "name": string, "email": string, "active": bool}` (keys in that order).
   `name` and `email` are stored as sent (no trimming, no lower-casing). `active` is
   optional and defaults to `true`; when a boolean is sent it is stored as sent
   (`{"active": false}` creates an inactive agent). Unknown keys in the body are ignored.
   The agent is persisted: a subsequent `GET /api/agents` returns it.
2. **Validation of `name` and `email` (URS-N-02).** Each of the following is a client
   fault and returns **422** with `Content-Type: application/json` and the body
   `{"errors": {"<field>": "<message>"}}`, where `errors` is a JSON object keyed by the
   offending field name (`name` or `email`; both keys when both are invalid) and each
   value is a non-empty string (the first violation message for that field). Nothing is
   persisted in these cases.
   - `name` missing, `null`, not a string (for example `123`), empty or whitespace only
     (`""`, `"   "`), or longer than 120 characters -> key `name`.
   - `email` missing, `null`, not a string, empty or whitespace only, longer than 180
     characters, or not a syntactically valid email address (for example `not-an-email`,
     `ada@`) -> key `email`.
   - `active` present but not a boolean (for example `"yes"`) -> key `active`.
   The exact message texts are not prescribed except for point 3.
3. **Unique email (URS-F-01.2).** `POST /api/agents` with an `email` equal (exact,
   case-sensitive string comparison, matching the database unique index) to the email of
   an existing agent returns **422** with `Content-Type: application/json` and the body
   `{"errors": {"email": "An agent with this email already exists."}}`. No second agent is
   created (`GET /api/agents` still lists one agent with that email). The request must
   never surface the database's `UniqueConstraintViolationException` as a 500: if the
   developer checks via the repository first, a flush that still fails on the unique
   index (a concurrent insert) must be caught and mapped to the same 422 body.
4. **Other client faults before the controller runs (URS-N-02).** A body that is not
   valid JSON (for example `{not json`), or an empty body, returns **400**; a body sent
   with a non-JSON `Content-Type` (for example `text/plain`) returns **415**. In both
   cases the response has `Content-Type: application/json` and a body
   `{"error": "<message>"}` with a non-empty string message. Never HTML, never 500.
5. **`GET /api/agents` (URS-F-01.3).** Returns **200** with `Content-Type:
   application/json` and `{"items": [<Agent::toArray()>, ...], "count": n}`, `count`
   equal to `count(items)`. Ordering: active agents first (`active` DESC), then by `id`
   ascending within each group, so the order is deterministic. With no agents the body
   is exactly `{"items": [], "count": 0}`. Query parameters are ignored (no filter, no
   pagination in this item).
6. **Repository.** The ordering lives in `App\Repository\AgentRepository` as
   `public function findActiveFirst(): array` (`list<Agent>`), implemented as a Doctrine
   query (`findBy([], ['active' => 'DESC', 'id' => 'ASC'])` or an equivalent query
   builder), not as PHP-side sorting in the controller.
7. **Controller and DTO (AGENTS.md).** New `src/Controller/AgentController.php`
   extending `AbstractController`, `#[Route('/api/agents')]` at class level, actions
   `list` (`GET ''`, name `agent_list`) and `create` (`POST ''`, name `agent_create`).
   `create` binds its input with `#[MapRequestPayload]` on a `final readonly` DTO (new
   `src/Dto/CreateAgentRequest.php` or similar) that uses constructor property promotion
   and `#[Assert\...]` constraints on the properties. No `json_decode()` and no manual
   `SerializerInterface`/`ValidatorInterface` calls in the controller. The controller
   stays thin: map DTO to `new Agent(...)`, persist, flush, return JSON.
8. **Existing endpoints are unchanged.** `GET/POST /api/tickets`,
   `GET /api/tickets/{id}`, `PATCH /api/tickets/{id}/status` and `GET /api/stats` keep
   their status codes and JSON bodies. Whatever mechanism turns the 422/400/415 of
   points 2-4 into JSON must apply only to `HttpExceptionInterface` exceptions (it must
   not catch arbitrary `\Throwable`, so the pre-existing 500 on `GET /api/tickets/9999`
   is left as-is for its own item) and must not alter responses that controllers
   already return themselves.

## Acceptance criteria

Each criterion is one test method in a new `tests/Api/U1AgentsCreateListTest.php`
extending `ApiTestCase` (use `postJson()` and `body()`; `createAgent()` for set-up; read the
header with `$this->client->getResponse()->headers->get('Content-Type')`). The existing
`tests/Api/TicketApiTest.php` and `tests/Api/F0SlaFoundationTest.php` must stay green
unchanged.

- [ ] AC1 (URS-F-01.3) `POST /api/agents` with `{"name": "Ada", "email": "ada@example.test"}`
      returns 201, `Content-Type` starting with `application/json`, and a body whose
      `array_keys` are exactly `['id', 'name', 'email', 'active']`, with `id` an int,
      `name === 'Ada'`, `email === 'ada@example.test'`, `active === true`.
- [ ] AC2 (URS-F-01.3) After AC1's POST, `GET /api/agents` returns 200 with
      `count === 1` and `items[0]` identical to the POST response body (same id, name,
      email, active).
- [ ] AC3 (URS-F-01.3) `POST /api/agents` with `{"name": "Bob", "email": "bob@example.test", "active": false}`
      returns 201 with `active === false`; with `{"name": "Cy", "email": "cy@example.test", "colour": "blue"}`
      (unknown key) returns 201 and the body has no `colour` key.
- [ ] AC4 (URS-N-02) Missing fields are a JSON 422: `POST /api/agents` with `{}` returns
      422, `Content-Type` starting with `application/json`, and a body with exactly the
      top-level key `errors` whose value has both keys `name` and `email`, each a
      non-empty string. `GET /api/agents` then returns `count === 0`.
- [ ] AC5 (URS-N-02) Blank and oversized `name` are rejected, never 500: for each body
      `{"name": "", "email": "x@example.test"}`, `{"name": "   ", "email": "x@example.test"}`,
      `{"name": null, "email": "x@example.test"}`, `{"name": 123, "email": "x@example.test"}`
      and `{"name": "<121 x's>", "email": "x@example.test"}`, `POST /api/agents` returns
      422 with `Content-Type` starting with `application/json` and `errors.name` a
      non-empty string and no `errors.email` key.
- [ ] AC6 (URS-N-02) Malformed `email` is rejected, never 500: for each body
      `{"name": "Ada", "email": ""}`, `{"name": "Ada", "email": "not-an-email"}`,
      `{"name": "Ada", "email": "ada@"}`, `{"name": "Ada", "email": null}` and
      `{"name": "Ada", "email": "<170 a's>@example.test"}` (181+ characters),
      `POST /api/agents` returns 422 with `Content-Type` starting with `application/json`
      and `errors.email` a non-empty string and no `errors.name` key.
- [ ] AC7 (URS-N-02) A non-boolean `active` is a 422: `POST /api/agents` with
      `{"name": "Ada", "email": "ada@example.test", "active": "yes"}` returns 422 with
      JSON `errors.active` a non-empty string, and `GET /api/agents` then returns
      `count === 0`.
- [ ] AC8 (URS-F-01.2) Duplicate email via the API: `createAgent('Ada', 'ada@example.test')`
      then `POST /api/agents` with `{"name": "Ada Twin", "email": "ada@example.test"}`
      returns 422, `Content-Type` starting with `application/json`, and the body exactly
      `['errors' => ['email' => 'An agent with this email already exists.']]`;
      `GET /api/agents` then returns `count === 1` and `items[0]['name'] === 'Ada'`.
- [ ] AC9 (URS-F-01.2) Duplicate email, both via the API: `POST /api/agents`
      `{"name": "A", "email": "dup@example.test"}` returns 201, a second identical POST
      returns 422 with `errors.email` as in AC8, and a third POST with
      `{"name": "B", "email": "Dup@example.test"}` (different case) returns 201
      (comparison is exact, like the unique index); `GET /api/agents` returns `count === 2`.
- [ ] AC10 (URS-N-02) Malformed JSON is a JSON 400, not HTML and not 500:
      `$this->client->request('POST', '/api/agents', server: ['CONTENT_TYPE' => 'application/json'], content: '{not json')`
      returns 400 with `Content-Type` starting with `application/json` and a body whose
      `error` key is a non-empty string (`body()` must decode without throwing). The same
      request with `content: ''` (empty body) also returns 400 with the same shape.
- [ ] AC11 (URS-N-02) Unsupported content type is a JSON 415:
      `$this->client->request('POST', '/api/agents', server: ['CONTENT_TYPE' => 'text/plain'], content: '{"name":"Ada","email":"ada@example.test"}')`
      returns 415 with `Content-Type` starting with `application/json` and a body whose
      `error` key is a non-empty string.
- [ ] AC12 (URS-F-01.3) Active first: `createAgent('Zed', 'zed@example.test', false)`,
      `createAgent('Ann', 'ann@example.test', true)`, `createAgent('Bea', 'bea@example.test', false)`,
      `createAgent('Cal', 'cal@example.test', true)` in that order; `GET /api/agents`
      returns 200, `count === 4`, and `array_column($items, 'name')` is exactly
      `['Ann', 'Cal', 'Zed', 'Bea']` (active ones first, each group in id order), with
      `array_column($items, 'active')` equal to `[true, true, false, false]`.
- [ ] AC13 (URS-F-01.3) Empty list: with no agents, `GET /api/agents` returns 200 and the
      body is exactly `['items' => [], 'count' => 0]`; `array_keys($body)` is
      `['items', 'count']`.
- [ ] AC14 (URS-F-01.3) Item shape: after one `createAgent()`, `GET /api/agents` returns
      `items[0]` with `array_keys` exactly `['id', 'name', 'email', 'active']` and values
      equal to `$agent->toArray()`.
- [ ] AC15 (URS-N-02) Existing endpoints keep their responses: `POST /api/tickets` with
      a valid body still returns 201 with keys
      `id, title, description, priority, status, createdAt, dueAt, assignee`;
      `PATCH /api/tickets/{id}/status` with `{"status": "done"}` still returns 422 with
      `error === 'Invalid status'`; `GET /api/stats` still returns 200 with exactly the
      keys `total, open, in_progress, closed, closed_ratio`.
- [ ] AC16 Repository ordering (service-level, `static::getContainer()->get(AgentRepository::class)`
      inside the same test class): after the four agents of AC12,
      `findActiveFirst()` returns four `Agent` instances whose `getName()` sequence is
      `['Ann', 'Cal', 'Zed', 'Bea']`.

## Out of scope

- No `PATCH /api/agents/{id}` (URS-F-01.4), no `GET /api/agents/{id}`, no delete, no
  filtering or pagination on the list, no assignment endpoints (URS-F-02), no audit log
  requirement (URS-N-01 covers activation changes, which arrive with URS-F-01.4).
- Do not normalise emails (no trimming, no lower-casing); uniqueness is exact, like the
  index. A case-insensitive rule would be a separate requirement.
- Do not modify `src/Entity/Agent.php` (it already has everything this item needs; adding
  `#[Assert\...]` to the entity is not required, constraints go on the DTO), `src/Entity/Ticket.php`,
  `src/Service/TicketStats.php`, `src/Command/SimulateTrafficCommand.php`, `migrations/`,
  `config/`, `composer.json`, `tests/Api/ApiTestCase.php`, `tests/Api/TicketApiTest.php`,
  `tests/Api/F0SlaFoundationTest.php`. No new migration (no schema change).
- Do not touch `src/Controller/TicketController.php` or `src/Repository/TicketRepository.php`
  at all: another change is in flight on those two files. This item adds new files only
  (`AgentController`, the DTO, optionally one event listener) plus one method on
  `AgentRepository`.
- Do not change how existing endpoints report errors, and do not fix the pre-existing
  500 on `GET /api/tickets/{unknown id}`; only `HttpExceptionInterface` exceptions may be
  turned into JSON (Required behaviour 8). Do not configure `framework.exceptions` or
  swap the error renderer project-wide.
- Do not add a `Location` header, HATEOAS links, or any key beyond `Agent::toArray()` to
  the agent JSON.

## Hints for the developer

- DTO: `final readonly class CreateAgentRequest` with
  `__construct(public string $name = '', public string $email = '', public bool $active = true)`.
  Giving `name`/`email` an empty-string default makes a *missing* key reach
  `#[Assert\NotBlank]` (readable message, key present in `errors`) instead of a
  constructor-argument error; `null` or a wrong type is still collected by the Serializer
  (`COLLECT_DENORMALIZATION_ERRORS` is on in the resolver) and surfaces as a violation on
  the same property path, so AC5/AC6/AC7 all land in the same 422 path. Constraints:
  `#[Assert\NotBlank] #[Assert\Length(max: 120)]` on `name`,
  `#[Assert\NotBlank] #[Assert\Email] #[Assert\Length(max: 180)]` on `email`. Default
  Symfony messages are fine; no translator is installed, so they come out in English.
- Action signature: `public function create(#[MapRequestPayload(acceptFormat: 'json')] CreateAgentRequest $payload): JsonResponse`.
  `acceptFormat: 'json'` is what makes AC11 a deterministic 415.
- Turning the resolver's exceptions into JSON: the resolver throws before the action runs,
  so the controller cannot catch them. The idiomatic place is one small
  `#[AsEventListener(event: KernelEvents::EXCEPTION)]` class under `src/EventListener/`
  that, for requests whose path starts with `/api/` and for `$event->getThrowable()
  instanceof HttpExceptionInterface`: if `getPrevious()` is a
  `Symfony\Component\Validator\Exception\ValidationFailedException`, builds
  `{"errors": {propertyPath => message}}` from `getViolations()` (first message per
  path), otherwise `{"error": getMessage()}`; then `$event->setResponse(new JsonResponse($body, $e->getStatusCode(), $e->getHeaders()))`.
  Leave every other throwable alone. (Side effect: a 404 for an unknown `/api/...` route
  also becomes JSON, which URS-N-02 wants; it changes no existing endpoint.)
- Unique email: the simplest fit is a repository lookup in the action
  (`$agents->findOneBy(['email' => $payload->email])`) returning
  `$this->json(['errors' => ['email' => 'An agent with this email already exists.']], Response::HTTP_UNPROCESSABLE_ENTITY)`,
  plus a `try { flush } catch (UniqueConstraintViolationException)` returning the same
  body for the race case. A custom `#[UniqueAgentEmail]` constraint with a
  `ConstraintValidator` that autowires `AgentRepository` is the more Symfony-ish
  alternative and also satisfies the spec; `#[UniqueEntity]` does not apply because the
  DTO is not an entity. Either way the message text must be exactly the one in Required
  behaviour 3.
- Repository: `public function findActiveFirst(): array { return $this->findBy([], ['active' => 'DESC', 'id' => 'ASC']); }`.
  SQLite stores booleans as 0/1, so `DESC` puts active ones first.
- Logging an info line on creation (like `TicketController::create` does for tickets) is
  welcome but not asserted.
- Tests: `postJson('/api/agents', [...])` for every JSON case; for AC10/AC11 call
  `$this->client->request()` directly as shown so the raw content and content type are
  under the test's control. Build the long strings with `str_repeat('x', 121)` and
  `str_repeat('a', 170).'@example.test'`.
