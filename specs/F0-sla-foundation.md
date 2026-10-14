# F0: Agent entity, Ticket assignee relation and stored due time, SLA target constants, migration, ApiTestCase

**Kind:** foundation
**URS:** docs/urs/URS-002-sla-module.md
**Evidence:** URS-F-01.1, URS-F-02.1, URS-F-03.1, URS-F-03.2, URS-F-03.3, URS-F-03.4
**Constraints:** URS-N-03

A foundation item lays the persistence and test groundwork that every later item of the
same URS builds on. It adds **no endpoints**. It is deliberately boring so that the
endpoint items (U1 agents, U2/U3 assignment, U4 breach listing, U5 report) can each be
small.

## Current behaviour

- `src/Entity/Ticket.php:12-43` is the only entity. Columns: `id`, `title` (120),
  `description` (text), `priority` (string 16, free text; `Ticket::PRIORITIES` at line 14
  lists `low|normal|high` but nothing enforces it, and the traffic simulator posts
  `urgent`, see `src/Command/SimulateTrafficCommand.php:57`), `status` (string 16,
  default `open`), `createdAt` (`DateTimeImmutable`, set to "now" in the constructor at
  line 42). There is no assignee, no due time, no SLA constant, and no `Agent` entity.
- `Ticket::toArray()` (`src/Entity/Ticket.php:81-91`) emits exactly `id`, `title`,
  `description`, `priority`, `status`, `createdAt` (`DATE_ATOM`). Every ticket endpoint
  in `src/Controller/TicketController.php` (`GET /api/tickets` line 28, `POST
  /api/tickets` line 36, `GET /api/tickets/{id}` line 51, `PATCH /api/tickets/{id}/status`
  line 58) returns this array; the list wraps it as `{"items": [...], "count": n}`.
- `migrations/Version20261014130059.php:23` is the single migration; it creates the
  `ticket` table. The dev database `var/data_dev.db` has `doctrine_migration_versions`
  and three ticket rows (priorities `high`, `low`, `urgent`), so a new migration will run
  against existing rows.
- `tests/Api/TicketApiTest.php:16-82` is the only test class: a `final` `WebTestCase`
  that drops and recreates the schema with `SchemaTool` in `setUp()` (lines 20-30) and
  owns `private` helpers `postJson()` (line 72) and `body()` (line 78). There is no
  `tests/Api/ApiTestCase.php`, no `patchJson()` and no entity factory helpers.
  `testChangeStatus` (line 55) issues its PATCH through `$this->client->request()`
  directly.
- `src/Service/TicketStats.php` and `GET /api/stats` do not touch any field this item
  adds; they must keep working unchanged.

## Required behaviour

1. **Entities.**
   1. New `src/Entity/Agent.php`, `#[ORM\Entity(repositoryClass: AgentRepository::class)]`,
      table `agent`:
      - `Agent::$id`: `?int`, `#[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column]`.
      - `Agent::$name`: `string`, `#[ORM\Column(length: 120)]`, not nullable.
      - `Agent::$email`: `string`, `#[ORM\Column(length: 180, unique: true)]`, not
        nullable. The unique index is the storage side of URS-F-01.2; the validation
        error itself is built in U1, not here.
      - `Agent::$active`: `bool`, `#[ORM\Column]`, not nullable, defaults to `true`.
      - Constructor `__construct(string $name, string $email, bool $active = true)`.
      - Accessors later items call: `getId(): ?int`, `getName(): string`,
        `getEmail(): string`, `isActive(): bool`, `setActive(bool $active): void`,
        and `toArray(): array` returning exactly
        `{"id": int, "name": string, "email": string, "active": bool}`.
   2. New `src/Repository/AgentRepository.php` extending `ServiceEntityRepository<Agent>`,
      same shape as `TicketRepository`. No custom query methods yet (U1 adds the
      "active first" ordering).
   3. `Ticket::$assignee`: `?Agent`, `#[ORM\ManyToOne(targetEntity: Agent::class)]` with
      `#[ORM\JoinColumn(name: 'assignee_id', nullable: true, onDelete: 'SET NULL')]`,
      default `null`. No inverse side on `Agent`. Accessors: `getAssignee(): ?Agent`,
      `setAssignee(?Agent $assignee): void` (passing `null` unassigns; U2/U3 call these).
   4. `Ticket::$dueAt`: `?\DateTimeImmutable`, `#[ORM\Column(nullable: true)]`, column
      `due_at`. The constructor always sets it (point 2 below), so every ticket created
      through the application has a non-null due time; the column is nullable only so
      that the generated SQLite migration can carry the three pre-existing dev rows
      (URS-F-03.1 applies "at creation"; rows that predate the module keep `null`).
      Accessor: `getDueAt(): ?\DateTimeImmutable`. No `setDueAt()`: the due time is
      stored once and never recomputed (URS-F-03.3).
   5. `Ticket::__construct` gains two optional trailing parameters and keeps the three
      existing ones in their current order, so every existing call site compiles
      unchanged:
      `__construct(string $title, string $description, string $priority, ?\DateTimeImmutable $createdAt = null, ?\DateTimeImmutable $dueAt = null)`.
      - `$createdAt ?? new \DateTimeImmutable()` becomes `createdAt`.
      - `$dueAt ?? createdAt + slaTargetHours(priority)` becomes `dueAt`. The explicit
        `$dueAt` exists for the test factory (breach set-ups) and never changes once the
        object is constructed.
   6. `Ticket::getCreatedAt()` and every other existing accessor keep their signatures.
2. **Constants.** On `App\Entity\Ticket`:
   - `public const array SLA_TARGET_HOURS = ['low' => 72, 'normal' => 24, 'high' => 8];`
     (URS-F-03.1).
   - `public const string SLA_DEFAULT_PRIORITY = 'normal';` (URS-F-03.2).
   - `public static function slaTargetHours(string $priority): int` returning
     `self::SLA_TARGET_HOURS[$priority] ?? self::SLA_TARGET_HOURS[self::SLA_DEFAULT_PRIORITY]`,
     so `low -> 72`, `normal -> 24`, `high -> 8`, and any other string (`urgent`, `''`,
     `LOW`) `-> 24`. The lookup is case-sensitive; `PRIORITIES` is not changed. The due
     time is `createdAt->modify(sprintf('+%d hours', hours))`, computed in the
     constructor only (not in a controller or service).
3. **Migration.** Exactly one new file under `migrations/`, generated with
   `tools/php bin/console doctrine:migrations:diff -n` and applied with
   `tools/php bin/console doctrine:migrations:migrate -n` (dev) and
   `tools/php bin/console doctrine:migrations:migrate -n --env=test` (test). It must:
   - create table `agent` (`id`, `name VARCHAR(120) NOT NULL`, `email VARCHAR(180) NOT
     NULL`, `active BOOLEAN NOT NULL`) with a unique index on `email`;
   - add `assignee_id INTEGER DEFAULT NULL` and `due_at DATETIME DEFAULT NULL` to
     `ticket`, with the foreign key `assignee_id -> agent(id) ON DELETE SET NULL` and an
     index on `assignee_id`;
   - keep every existing `ticket` row and every existing column value (the SQLite diff
     recreates the table through a `__temp__ticket` copy; because both new columns are
     nullable the copy succeeds without hand-written SQL). Pre-existing rows end with
     `assignee_id = NULL` and `due_at = NULL`.
   `Version20261014130059.php` is not modified.
4. **JSON shape (URS-N-03).** `Ticket::toArray()` keeps every existing key with its
   current value and format and appends, in this order:
   - `"dueAt"`: `string` in ISO 8601 via `DATE_ATOM` (same format as `createdAt`, for
     example `"2026-10-15T13:00:59+00:00"`), or `null` when `dueAt` is null
     (URS-F-03.4);
   - `"assignee"`: always `null` in this item. It is a placeholder so the key exists in
     every ticket response from now on; U3 (URS-F-02.4) replaces it with
     `{"id": int, "name": string}` when an assignee is set. No key is renamed or
     removed; `GET /api/stats` is unchanged.
5. **Test base.** New `tests/Api/ApiTestCase.php`, `abstract class ApiTestCase extends
   WebTestCase`, in namespace `App\Tests\Api`:
   - `protected KernelBrowser $client;` and `protected function setUp(): void` doing
     exactly what `TicketApiTest::setUp()` does today (create client, `SchemaTool`
     drop + create of all metadata, which now includes `agent`).
   - `protected function em(): EntityManagerInterface` (from `static::getContainer()`).
   - `protected function postJson(string $uri, array $payload): void` and
     `protected function patchJson(string $uri, array $payload): void`: send the method
     to `$uri` with `CONTENT_TYPE: application/json` and `json_encode($payload,
     JSON_THROW_ON_ERROR)` as content (`['agentId' => null]` must therefore encode as
     `{"agentId":null}`, not be dropped).
   - `protected function body(): array`: `json_decode` of the last response with
     `JSON_THROW_ON_ERROR`.
   - `protected function createAgent(string $name = 'Ada', string $email = 'ada@example.test', bool $active = true): Agent`:
     persists and flushes a new `Agent`, returns it (id populated).
   - `protected function createTicket(string $title = 'Ticket', string $description = 'Body', string $priority = 'normal', ?\DateTimeImmutable $createdAt = null, ?\DateTimeImmutable $dueAt = null, ?Agent $assignee = null, string $status = 'open'): Ticket`:
     builds the ticket through the constructor (passing `$createdAt` and `$dueAt`
     through), applies `setAssignee()` and `setStatus()`, persists, flushes, returns it.
     Breach set-ups in U4/U5 will call it with `createdAt`/`dueAt` in the past.
   - `tests/Api/TicketApiTest.php` is moved onto it: `extends ApiTestCase`, its own
     `setUp()`, `$client` property, `postJson()` and `body()` are deleted, and the three
     test methods keep their bodies and assertions character for character.
6. **Skeleton.** Not applicable: the repository is not empty.

## Acceptance criteria

- [ ] AC1 `tools/php bin/console doctrine:migrations:migrate -n` succeeds on a database
      that already contains rows from the previous schema (the current `var/data_dev.db`
      with its three tickets, including the `urgent` one), and again on an empty one
      (a fresh SQLite file, both migrations run in order). Afterwards `SELECT id, title,
      priority, status, created_at, assignee_id, due_at FROM ticket` returns the three
      original rows unchanged with `assignee_id` and `due_at` both `NULL`, and the
      `agent` table exists with a unique index on `email`.
- [ ] AC2 A `KernelTestCase` persists one `Agent` (`name`, `email`, `active = false`) and
      one `Ticket` constructed with explicit `createdAt`, explicit `dueAt` and that
      agent as assignee, flushes, clears the entity manager, reloads both by id, and
      reads back identical values: `getName()`, `getEmail()`, `isActive() === false`;
      `getTitle()`, `getDescription()`, `getPriority()`, `getStatus()`,
      `getCreatedAt() == createdAt`, `getDueAt() == dueAt`, and
      `getAssignee()->getId() === agent id`.
- [ ] AC3 The existing suite passes unchanged: `tools/php vendor/bin/phpunit` is green
      and `testCreateAndListTicket`, `testChangeStatus`, `testStatsWithTickets` keep
      their assertions.
- [ ] AC4 `GET /api/tickets` after one `POST /api/tickets` still returns `count` and
      `items[0]` with every existing key (`id`, `title`, `description`, `priority`,
      `status`, `createdAt`), plus `dueAt` as a string parseable by
      `\DateTimeImmutable::createFromFormat(DATE_ATOM, ...)` and `assignee` present with
      value `null` (`array_key_exists('assignee', $item)` is true).
- [ ] AC5 `tests/Api/ApiTestCase.php` exists, is abstract, extends `WebTestCase`, exposes
      `postJson()`, `patchJson()`, `body()`, `createAgent()`, `createTicket()` as
      `protected`, and `TicketApiTest` extends it without changing its assertions.
- [ ] AC6 (URS-F-01.1) `createAgent('Ada', 'ada@example.test')` yields an `Agent` with a
      non-null id, `isActive() === true` by default, and `toArray()` equal to
      `['id' => <id>, 'name' => 'Ada', 'email' => 'ada@example.test', 'active' => true]`.
- [ ] AC7 (URS-F-01.1) Persisting a second `Agent` with the same email as an existing
      one and flushing throws `Doctrine\DBAL\Exception\UniqueConstraintViolationException`
      (the unique index is in place; the 422 mapping is U1's job).
- [ ] AC8 (URS-F-02.1) `createTicket()` with no assignee has `getAssignee() === null`;
      after `setAssignee($agent)` + flush + clear + reload, `getAssignee()->getId()` is
      the agent's id; after `setAssignee(null)` + flush + clear + reload it is `null`
      again.
- [ ] AC9 (URS-F-03.1) `POST /api/tickets` with priority `low`, then `normal`, then
      `high` returns 201 each time with `dueAt - createdAt` (both parsed from the JSON
      with `DATE_ATOM`) equal to exactly 72 h, 24 h and 8 h (259200, 86400, 28800
      seconds).
- [ ] AC10 (URS-F-03.1) `new Ticket('t', 'd', 'high', new \DateTimeImmutable('2026-01-01T00:00:00+00:00'))`
      has `getDueAt()` equal to `2026-01-01T08:00:00+00:00`; `Ticket::slaTargetHours()`
      returns `72`, `24`, `8` for `low`, `normal`, `high`.
- [ ] AC11 (URS-F-03.2) `POST /api/tickets` with priority `urgent` (the value the traffic
      simulator sends) returns 201 with `dueAt - createdAt` equal to 24 h, and
      `Ticket::slaTargetHours('urgent')`, `slaTargetHours('')`, `slaTargetHours('LOW')`
      all return `24`.
- [ ] AC12 (URS-F-03.3) `createTicket(priority: 'high', createdAt: $t0, dueAt: $t0 + 1 h)`
      then flush, clear, reload: `getDueAt()` equals `$t0 + 1 h`, not `$t0 + 8 h`; the
      due time is read from the `due_at` column, never derived from `priority` on read.
      `Ticket` has no public `setDueAt()` method.
- [ ] AC13 (URS-F-03.4) `POST /api/tickets` and `GET /api/tickets/{id}` both return a
      `dueAt` string matching `/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/`
      (ISO 8601, `DATE_ATOM`), identical in both responses for the same ticket.
- [ ] AC14 (URS-N-03) After one `POST /api/tickets`, each of `POST /api/tickets` (201),
      `GET /api/tickets` (200), `GET /api/tickets/{id}` (200) and `PATCH
      /api/tickets/{id}/status` with `{"status": "closed"}` (200) returns a ticket object
      whose key set is exactly `id, title, description, priority, status, createdAt,
      dueAt, assignee`; `GET /api/stats` still returns exactly `total, open,
      in_progress, closed, closed_ratio`.
- [ ] AC15 (URS-N-03) `GET /api/tickets` with no tickets still returns 200
      `{"items": [], "count": 0}`.

## Out of scope

- No new routes or controllers: no `/api/agents`, no `/api/tickets/{id}/assignee`, no
  `breached` query parameter, no `/api/reports/sla`. No validation DTOs in `src/Dto/`.
  No `#[Assert\...]` on `Agent` (U1 adds the email validation and 422 mapping).
- Do not enforce `Ticket::PRIORITIES` anywhere; unknown priorities keep being accepted
  by `POST /api/tickets` exactly as today, they simply get the normal target.
- Do not change the behaviour, status codes or error handling of any existing endpoint
  (`GET /api/tickets`, `POST /api/tickets`, `GET /api/tickets/{id}`, `PATCH
  /api/tickets/{id}/status`, `GET /api/stats`) except adding the two JSON keys listed in
  Required behaviour 4. The pre-existing 500 on `GET /api/tickets/9999` and the
  division by zero in `TicketStats` with an empty table are not this item's concern.
- Do not add `assignee` logging, the `{id, name}` assignee object, agent ordering, or
  any breach computation; those belong to U1 to U5.
- Do not modify `migrations/Version20261014130059.php`, `src/Command/SimulateTrafficCommand.php`,
  `src/Service/TicketStats.php`, `config/`, `composer.json`.
- Do not set `due_at` for the three pre-existing dev rows; they stay `null`.

## Hints for the developer

- Doctrine attributes on the entity; `#[ORM\ManyToOne(targetEntity: Agent::class)]` with
  `#[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]` for the optional relation;
  `#[ORM\Column(length: 180, unique: true)]` for the email. `#[ORM\Column(nullable:
  true)]` on a `?\DateTimeImmutable` property maps to `datetime_immutable` like
  `createdAt` already does.
- Put the time arithmetic in the `Ticket` constructor (`modify('+N hours')` on the
  immutable `createdAt`), backed by the `public const` map and the static lookup, not in
  `TicketController::create()`, so U4/U5 and the test factory reuse it.
- Keep the migration file as generated by `doctrine:migrations:diff -n`; do not hand-edit
  SQL unless the diff is wrong for SQLite, and then say so in the report. Run
  `doctrine:migrations:migrate -n` for both `dev` and `test` (`--env=test`); PHPUnit
  resets the test schema with `SchemaTool` anyway, so the migration's real consumer is
  `var/data_dev.db`.
- SQLite does not enforce `ON DELETE SET NULL` unless `PRAGMA foreign_keys` is on; the
  clause still belongs in the mapping and the migration, and no test needs to delete an
  agent.
- `ApiTestCase` helpers are `protected`; keep `TicketApiTest` `final` and let it simply
  drop its duplicated code. Add the `KernelTestCase` for AC2/AC7/AC8/AC12 under
  `tests/Entity/` or `tests/Api/`, either is fine for the tester.
