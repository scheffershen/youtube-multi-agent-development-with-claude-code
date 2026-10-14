# F0: <title from backlog>

**Kind:** foundation
**URS:** <path>
**Evidence:** <URS ids from backlog>

A foundation item lays the persistence and test groundwork that every later item of the
same URS builds on. It adds **no endpoints**. It is deliberately boring so that the
endpoint items can each be small.

## Current behaviour

What exists today in `src/Entity/`, `migrations/`, `tests/`. Cite files. If the repository
has no entities at all, say "empty skeleton" and include the skeleton setup in the
required behaviour below.

## Required behaviour

1. **Entities.** One numbered line per new entity or new column, with type, nullability,
   default, uniqueness and relation. Example: "`Ticket::$assignee`: nullable many-to-one
   to `Agent`, `ON DELETE SET NULL`." Name the getters and setters that later items will
   call, so the endpoint developers do not invent their own.
2. **Constants.** Any enumerations or lookup tables the URS fixes (for example SLA targets
   per priority) as `public const` on the owning entity or a dedicated value class. State
   where.
3. **Migration.** Generated with `doctrine:migrations:diff -n`, applied with
   `doctrine:migrations:migrate -n` to both `dev` and `test`. Existing rows must survive:
   new non-nullable columns need a default or a two-step migration.
4. **JSON shape.** If an existing `toArray()` gains fields, list them here with their
   exact key names and formats. No existing key is renamed or removed (backward
   compatibility is usually a URS-N constraint; cite it).
5. **Test base.** `tests/Api/ApiTestCase.php`: an abstract `WebTestCase` with the schema
   reset in `setUp()`, `postJson()`, `patchJson()`, `body()` helpers, and factory helpers
   for every new entity (`createAgent(...)`, `createTicket(...)`). Existing tests may be
   moved onto it but must not change their assertions.
6. **Skeleton (only if empty repository).** `composer create-project symfony/skeleton`,
   then require `orm-pack`, `monolog-bundle`, `validator`, `serializer`, and `test-pack`
   as dev; SQLite `DATABASE_URL`; the `tools/` wrappers.

## Acceptance criteria

- [ ] AC1 `tools/php bin/console doctrine:migrations:migrate -n` succeeds on a database
      that already contains rows from the previous schema, and again on an empty one.
- [ ] AC2 A `KernelTestCase` persists one instance of each new entity with every new
      column populated, flushes, clears, reloads, and reads back identical values
      (including relations).
- [ ] AC3 The existing suite passes unchanged.
- [ ] AC4 `GET /api/tickets` still returns every existing key; new keys are present with
      the documented format (for example `dueAt` in ISO 8601 or `null`).
- [ ] AC5 `tests/Api/ApiTestCase.php` exists and at least one existing test class extends
      it without changing its assertions.

## Out of scope

- No new routes or controllers. No validation DTOs. No business rules beyond defaults
  needed to make the schema valid.
- Do not change behaviour of any existing endpoint except adding the listed JSON keys.

## Hints for the developer

- Doctrine attributes on the entity; `#[ORM\ManyToOne]` with `#[ORM\JoinColumn(nullable:
  true, onDelete: 'SET NULL')]` for optional relations.
- Put time arithmetic in the entity constructor or a small value class, not in a
  controller, so later items reuse it.
- Keep the migration file as generated; do not hand-edit SQL unless the diff is wrong for
  SQLite, and then say so in the report.
