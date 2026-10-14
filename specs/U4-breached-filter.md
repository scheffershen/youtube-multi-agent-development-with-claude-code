# U4: GET /api/tickets?breached=true|false filter, combinable with the status filter

**Kind:** feature
**Evidence:** URS-F-04.1, URS-F-04.2, URS-F-04.3 (docs/urs/URS-002-sla-module.md, section 4; "breached" is defined in section 3)
**Constraints:** URS-N-02, URS-N-03 (docs/urs/URS-002-sla-module.md, section 5)
**Depends on:** F0 (`dueAt` column and `createTicket()` overrides), R1 (composable query builder)

## Current behaviour

`GET /api/tickets` is served by `TicketController::list()`
(`src/Controller/TicketController.php:32-46`). It reads only the `status` query value
(`$request->query->all()['status'] ?? null`, line 37), validates it against
`Ticket::STATUSES` (line 39), answers `{"error": "Invalid status", "allowed": [...]}` with
400 on failure (line 40), and otherwise calls `$this->tickets->findNewestFirst($status)`
(line 43). A `breached` query parameter is ignored entirely: `?breached=true` and
`?breached=anything` both return the full (or status-filtered) list with 200.
`TicketRepository` (`src/Repository/TicketRepository.php`) already exposes the composable
pieces R1 asked for: `createNewestFirstQueryBuilder()` (lines 30-35, alias `t`, newest
first, no WHERE), `withStatus(QueryBuilder, string)` (lines 38-41, `andWhere` +
`setParameter('status', ...)`) and `findNewestFirst(?string $status = null)` (lines 44-53).
No breach predicate exists anywhere in `src/`. `Ticket::$dueAt` is a nullable
`\DateTimeImmutable` column `due_at` (`src/Entity/Ticket.php:39-40`); the constructor
always fills it from `createdAt` plus `Ticket::slaTargetHours($priority)` unless an
explicit `$dueAt` is passed (lines 46-53), so every ticket created since F0 has a due
time and only rows that predate the F0 migration carry `null`. `Ticket::STATUSES` is
`['open', 'in_progress', 'closed']` (line 15); `closed` is the only terminal status.
`Ticket::toArray()` (lines 111-123) emits `dueAt` as a `DATE_ATOM` string or `null`.

## Required behaviour

1. **Definition of breached (URS-002 section 3).** A ticket is breached when, and only
   when, all three hold at evaluation time `now`:
   `status !== 'closed'` **and** `dueAt !== null` **and** `dueAt < now` (strictly in the
   past; a ticket whose `dueAt` equals `now` to the second is *not* breached). A ticket
   with `dueAt === null` is never breached, whatever its status or age. A `closed` ticket
   is never breached, however far past its due time it was closed. `now` is the server's
   current time when the request is handled.
2. **`GET /api/tickets` without a `breached` parameter is unchanged** (URS-N-03): every
   ticket, newest first (`createdAt` DESC, then `id` DESC), 200, envelope
   `{"items": [...], "count": n}`, exactly as R1 left it, with or without `?status=`.
3. **`GET /api/tickets?breached=true`** returns 200 with `items` containing only the
   tickets that are breached per point 1, in the same newest-first order, and
   `count === count(items)`. No match is `{"items": [], "count": 0}`, not an error.
4. **`GET /api/tickets?breached=false`** returns 200 with `items` containing exactly the
   tickets that are **not** breached per point 1 (so: every `closed` ticket, every ticket
   with `dueAt === null`, and every ticket whose `dueAt >= now`), newest first,
   `count === count(items)`. For any fixed `now`, the `true` set and the `false` set
   partition the full list of point 2.
5. **Any other `breached` value is a 400** (URS-F-04.2, URS-N-02). The accepted values
   are exactly the strings `true` and `false`, compared case-sensitively and exactly
   (same convention as R1's status). When the parameter is present with any other value
   the response is **400** with `Content-Type: application/json` and exactly the body
   `{"error": "Invalid breached", "allowed": ["true", "false"]}`. "Present but invalid"
   includes the empty string (`?breached=`), other spellings of a boolean (`?breached=1`,
   `?breached=0`, `?breached=True`, `?breached=TRUE`, `?breached=yes`, `?breached=no`),
   surrounding whitespace (`?breached=%20true`) and typos (`?breached=treu`). The ticket
   list is not queried in this case.
6. **(URS-N-02) A non-scalar value, `?breached[]=true` or `?breached[a]=true`, also
   returns the 400 JSON body of point 5**; it must never produce a 500 or an HTML error
   page. As in R1, read the raw query array, never `InputBag::get()`, which throws on
   array input and would be rendered as an HTML 400.
7. **Combination with the status filter (URS-F-04.3).** `?status=<s>&breached=<b>` applies
   both predicates (logical AND) on top of the newest-first order: `?status=open&breached=true`
   returns only open tickets that are breached; `?status=closed&breached=true` always
   returns `{"items": [], "count": 0}` (a closed ticket cannot be breached);
   `?status=closed&breached=false` returns every closed ticket. The parameters may appear
   in either order in the query string.
8. **Validation order when both parameters are invalid.** `status` is validated first,
   exactly as today, then `breached`. Therefore `?status=done&breached=true` returns the
   R1 body `{"error": "Invalid status", "allowed": ["open", "in_progress", "closed"]}`,
   `?status=open&breached=maybe` returns the body of point 5, and
   `?status=done&breached=maybe` returns the R1 status body. Both are 400 JSON.
9. **(URS-N-03) JSON shape unchanged.** The per-ticket object stays `Ticket::toArray()`
   with the key set `id, title, description, priority, status, createdAt, dueAt, assignee`
   in that order, with and without the new filter; the envelope stays exactly `items` and
   `count`. No key is added to the ticket JSON by this item (in particular no `breached`
   boolean on the item). `POST /api/tickets`, `GET /api/tickets/{id}`,
   `PATCH /api/tickets/{id}/status`, `PATCH /api/tickets/{id}/assignee`, `/api/agents*`
   and `GET /api/stats` are untouched.
10. **Repository design: one reusable breach predicate.** The breach definition of point 1
    lives in `App\Repository\TicketRepository` as a Doctrine `QueryBuilder` piece, in one
    place only, because item U5 (`GET /api/reports/sla`, URS-F-05) must count breached
    tickets with the *same* definition and will call this method rather than restate the
    predicate. Required API:
    1. `public function withBreached(\Doctrine\ORM\QueryBuilder $qb, bool $breached, \DateTimeImmutable $now): \Doctrine\ORM\QueryBuilder`
       adds, via `andWhere()` + `setParameter()` so it stacks after `withStatus()` or any
       other `andWhere`, and returns the same builder:
       - for `$breached === true`: `t.status <> :closedStatus AND t.dueAt IS NOT NULL AND t.dueAt < :now`;
       - for `$breached === false`: `(t.status = :closedStatus OR t.dueAt IS NULL OR t.dueAt >= :now)`;
       with `:closedStatus = 'closed'` and `:now = $now`. The parameter names must not
       collide with `:status` used by `withStatus()`. The method does not read the clock
       itself: `$now` is always passed in, so tests and U5 can pin it. It does not
       validate anything.
    2. `public function findNewestFirst(?string $status = null, ?bool $breached = null, ?\DateTimeImmutable $now = null): array`
       (`list<Ticket>`) keeps its first parameter and its behaviour when only that one is
       given, applies `withBreached($qb, $breached, $now ?? new \DateTimeImmutable())`
       only when `$breached` is not null, and returns `getQuery()->getResult()`. This is
       the method `list()` calls. Existing callers `findNewestFirst()` and
       `findNewestFirst($status)` keep compiling and returning the same lists.
    3. `createNewestFirstQueryBuilder()`, `withStatus()`, `findAllNewestFirst()` and
       `countByStatus()` keep their names, signatures and results.
11. **The controller stays thin.** `list()` reads the raw `breached` value next to
    `status`, maps `'true'` to `true`, `'false'` to `false`, absent to `null`, returns the
    400 of point 5 for anything else, then delegates to
    `findNewestFirst($status, $breached)` and maps with `toArray()` as today. The
    controller does not build DQL and does not filter in PHP. No logging change is
    required for this item.

## Acceptance criteria

Each criterion is one test method in a new `tests/Api/U4BreachedFilterTest.php` extending
`ApiTestCase`, in the style of `tests/Api/R1FilterTicketsByStatusTest.php` (a private
`at(string $modifier): \DateTimeImmutable` helper, `createTicket()` with explicit
`createdAt` and `dueAt` named arguments, `body()` for JSON). "T" below is the test's wall
clock; `T-1h` means `dueAt` one hour in the past, i.e. breached if not closed. To obtain a
ticket with `dueAt === null` (the constructor always fills it), create it normally and then
null the column directly, e.g.
`$this->em()->getConnection()->executeStatement('UPDATE ticket SET due_at = NULL WHERE id = ?', [$ticket->getId()]); $this->em()->clear();`.
Every existing test (`TicketApiTest`, `F0SlaFoundationTest`, `R1FilterTicketsByStatusTest`,
`U1AgentsCreateListTest`, `U3TicketAssigneeTest`, `tests/Unit/*`) must stay green unchanged.

- [ ] AC1 (URS-N-03) Without the parameter, behaviour is unchanged: create `open`
      (createdAt T-3h, dueAt T-1h, breached), `closed` (createdAt T-2h, dueAt T-1h),
      `open` (createdAt T-1h, dueAt T+1h); `GET /api/tickets` returns 200, `count === 3`
      and `items[*].createdAt` is strictly descending (newest first), identical to R1 AC1.
- [ ] AC2 (URS-F-04.1) `?breached=true` returns only breached tickets, newest first: create
      `open` (createdAt T-5h, dueAt T-1h, title `Old breached open`), `closed` (createdAt
      T-4h, dueAt T-3h, title `Closed past due`), `open` (createdAt T-3h, dueAt T+1h, title
      `Open not due`), `in_progress` (createdAt T-2h, dueAt T-30min, title `Breached in
      progress`), plus an `open` ticket (createdAt T-1h) whose `due_at` is set to NULL as
      described above (title `Legacy null due`). `GET /api/tickets?breached=true` returns
      200, `count === 2`, `array_column(items, 'title') === ['Breached in progress',
      'Old breached open']`, and no item has `status === 'closed'` or `dueAt === null`.
- [ ] AC3 (URS-F-04.2) `?breached=false` returns exactly the complement: same five tickets
      as AC2; `GET /api/tickets?breached=false` returns 200, `count === 3`,
      `array_column(items, 'title') === ['Legacy null due', 'Open not due', 'Closed past
      due']`; the union of the AC2 and AC3 `id` sets equals the `id` set of an unfiltered
      `GET /api/tickets` (`count === 5`) and the intersection is empty.
- [ ] AC4 `?breached=true` with no match is an empty list, not an error: create one `open`
      ticket with dueAt T+1h and one `closed` ticket with dueAt T-1h;
      `GET /api/tickets?breached=true` returns 200 and the body is exactly
      `['items' => [], 'count' => 0]`.
- [ ] AC5 (URS-F-04.2) An unknown value is a 400 naming the allowed values: create one
      `open` ticket; `GET /api/tickets?breached=yes` returns 400, the response
      `Content-Type` header starts with `application/json`, and the body is exactly
      `['error' => 'Invalid breached', 'allowed' => ['true', 'false']]`.
- [ ] AC6 (URS-N-02) Edge values are rejected the same way, never 500: for each of
      `?breached=`, `?breached=1`, `?breached=0`, `?breached=True`, `?breached=TRUE`,
      `?breached=%20true`, `?breached=treu`, `GET /api/tickets` returns 400 with a JSON
      body whose `error` key is `'Invalid breached'` and whose `allowed` key is
      `['true', 'false']`.
- [ ] AC7 (URS-N-02) A non-scalar value is a JSON 400, not a 500 and not HTML:
      `GET /api/tickets?breached[]=true` and `GET /api/tickets?breached[a]=true` both
      return 400 with `Content-Type` starting with `application/json` and the same body
      as AC5 (`body()` must decode without throwing).
- [ ] AC8 (URS-F-04.3) The breach filter combines with the status filter and both
      predicates apply: create `open` (createdAt T-4h, dueAt T-2h, title `Open breached`),
      `open` (createdAt T-3h, dueAt T+2h, title `Open fine`), `in_progress` (createdAt
      T-2h, dueAt T-1h, title `In progress breached`), `closed` (createdAt T-1h, dueAt
      T-1h, title `Closed past due`). Then:
      `GET /api/tickets?status=open&breached=true` returns 200, `count === 1`,
      `items[0]['title'] === 'Open breached'`;
      `GET /api/tickets?breached=true&status=in_progress` (reversed order) returns 200,
      `count === 1`, `items[0]['title'] === 'In progress breached'`;
      `GET /api/tickets?status=closed&breached=true` returns 200 and exactly
      `['items' => [], 'count' => 0]`;
      `GET /api/tickets?status=closed&breached=false` returns 200, `count === 1`,
      `items[0]['title'] === 'Closed past due'`;
      `GET /api/tickets?status=open&breached=false` returns 200, `count === 1`,
      `items[0]['title'] === 'Open fine'`.
- [ ] AC9 (URS-N-02) Combined invalid values are a JSON 400 with the status error taking
      precedence: create one `open` ticket; `GET /api/tickets?status=done&breached=true`
      returns 400 with body exactly `['error' => 'Invalid status', 'allowed' => ['open',
      'in_progress', 'closed']]`; `GET /api/tickets?status=open&breached=maybe` returns
      400 with body exactly `['error' => 'Invalid breached', 'allowed' => ['true',
      'false']]`; `GET /api/tickets?status=done&breached=maybe` returns 400 with the
      status body. All three have `Content-Type` starting with `application/json`.
- [ ] AC10 (URS-N-03) The JSON shape is unchanged with the new filter and other endpoints
      are untouched: create one `open` ticket with dueAt T-1h; `GET /api/tickets`,
      `GET /api/tickets?breached=true` and `GET /api/tickets?status=open&breached=true`
      all return 200 with `array_keys($body) === ['items', 'count']`,
      `array_keys($body['items'][0]) === ['id', 'title', 'description', 'priority',
      'status', 'createdAt', 'dueAt', 'assignee']`, and `items[0]` identical across the
      three responses. Then `POST /api/tickets` still returns 201, `GET /api/tickets/{id}`
      200, `PATCH /api/tickets/{id}/status` with `{"status": "done"}` still 422 with
      `error === 'Invalid status'`, and `GET /api/stats` still returns exactly the keys
      `total, open, in_progress, closed, closed_ratio`.
- [ ] AC11 The repository exposes one reusable, clock-pinned breach predicate
      (service-level test on `TicketRepository` from the container, as R1 AC10): create
      `open` (createdAt T-3h, dueAt T+1h, title `Future due`) and `open` (createdAt T-2h,
      dueAt T-1h, title `Past due`). Assert:
      (a) `withBreached($repository->createNewestFirstQueryBuilder(), true, $now)` returns
      a `Doctrine\ORM\QueryBuilder` whose `getDQL()` contains `t.dueAt`;
      (b) `findNewestFirst(null, true, new \DateTimeImmutable())` returns one ticket,
      `Past due`; `findNewestFirst(null, false, new \DateTimeImmutable())` returns one
      ticket, `Future due`;
      (c) pinning the clock changes the verdict without touching the rows:
      `findNewestFirst(null, true, $this->at('+2 hours'))` returns both tickets, `Future
      due` first (newest createdAt first), and `findNewestFirst(null, true,
      $this->at('-2 hours'))` returns none;
      (d) the boundary is strict: with `$now` equal to the `Past due` ticket's `getDueAt()`,
      `findNewestFirst(null, true, $now)` returns zero tickets and
      `findNewestFirst(null, false, $now)` returns both;
      (e) stacking works in both orders: `withBreached(withStatus($qb, 'open'), true, $now)`
      and `withStatus(withBreached($qb2, true, $now), 'open')` both execute without error
      and return the same single `Past due` ticket for `$now = new \DateTimeImmutable()`;
      (f) backward compatibility: `findNewestFirst()` and `findNewestFirst('open')` return
      the same two tickets as `findAllNewestFirst()`, newest first.

## Out of scope

- No change to the status filter's semantics, error body or validation (R1 stays as is);
  only its position in the validation order is stated (status first).
- No `GET /api/reports/sla`, no breach counts, no breach rate: that is item U5, which
  must reuse `withBreached()` from this item. Do not add a `countBreached()` or similar
  convenience method here; U5 will specify what it needs.
- No `breached` key on the ticket JSON, no `isBreached()` method on `Ticket`, no changes
  to `Ticket::toArray()`, `Ticket::STATUSES` or anything under `src/Entity/`.
- No pagination, no other filters (`priority`, `assignee`), no sorting parameter, no
  `since`/`now` query parameter to override the clock over HTTP.
- No installation of `symfony/clock` and no `ClockInterface` injection: the clock is a
  `\DateTimeImmutable` parameter on the repository method and `new \DateTimeImmutable()`
  as its default, which is enough for tests to pin it. (`composer.json` is untouched.)
- Do not touch `src/Service/`, `src/Command/SimulateTrafficCommand.php`, `src/Dto/`,
  `migrations/` (no schema change), `config/`, `composer.json`, `tests/Api/ApiTestCase.php`
  or any existing test file.
- Do not remove or rename `findAllNewestFirst()`, `countByStatus()`,
  `createNewestFirstQueryBuilder()` or `withStatus()`, and do not change their signatures.
- Do not change the error handling or status codes of `PATCH /api/tickets/{id}/status`
  (422 stays 422), `GET /api/tickets/{id}` (its pre-existing behaviour on an unknown id
  is not this item's concern), `POST /api/tickets`, `/api/agents*` or `GET /api/stats`.
- Do not add a global exception listener or change `framework.exceptions` /
  error-renderer configuration; produce the JSON 400 in the controller for this one
  parameter, as R1 did.
- Do not add a `src/Dto/` class or `#[MapQueryString]` for two optional scalar
  parameters; the R1 pattern (raw query array + `in_array`) is the required shape.

## Hints for the developer

- Controller: read `$request->query->all()['breached'] ?? null` beside the existing
  `status` line (`TicketController.php:37`). Accept only the exact strings `'true'` and
  `'false'`; a strict `match` or `\in_array($raw, ['true', 'false'], true)` handles
  arrays, `''`, `'1'`, `'True'` and `' true'` uniformly as invalid. Return
  `$this->json(['error' => 'Invalid breached', 'allowed' => ['true', 'false']], Response::HTTP_BAD_REQUEST)`
  after the status check so Required behaviour 8 holds. Do not use `InputBag::get()`,
  `InputBag::getBoolean()` (it maps `'1'`, `'yes'`, `'on'` to true and anything else to
  false, so `?breached=maybe` would silently become `false`), `filter_var(...,
  FILTER_VALIDATE_BOOL)` (same leniency) or `#[MapQueryParameter]` (HTML error page and
  404 default, see R1's hint).
- Repository: for `true`,
  `$qb->andWhere('t.status <> :closedStatus')->andWhere('t.dueAt IS NOT NULL')->andWhere('t.dueAt < :now')`;
  for `false`, one `andWhere('t.status = :closedStatus OR t.dueAt IS NULL OR t.dueAt >= :now')`
  (the parentheses matter because `andWhere` wraps the expression, so the OR group
  stays grouped when stacked after `t.status = :status`). Bind the date with an explicit
  type, `->setParameter('now', $now, \Doctrine\DBAL\Types\Types::DATETIME_IMMUTABLE)`,
  so SQLite compares the same `Y-m-d H:i:s` text format Doctrine stored in `due_at`
  (the column is `DATETIME`, stored as text; a mis-typed parameter compares strings
  lexically and silently returns wrong rows). `'closed'` is the literal from
  `Ticket::STATUSES`; bind it as a parameter rather than inlining it in DQL.
- `findNewestFirst()`: keep the existing `if (null !== $status)` block, add
  `if (null !== $breached) { $this->withBreached($qb, $breached, $now ?? new \DateTimeImmutable()); }`.
  Document in the method's docblock that U5 must call `withBreached()` for its counts.
- Tests: `createTicket(title: ..., status: ..., createdAt: $this->at('-3 hours'), dueAt: $this->at('-1 hour'))`
  is enough for every breach set-up; the NULL `due_at` row needs the DBAL `UPDATE` shown
  above followed by `$this->em()->clear()` so the identity map does not serve the stale
  object. For AC7 pass the query string literally (`'/api/tickets?breached[]=true'`). For
  AC11 fetch the repository with `static::getContainer()->get(TicketRepository::class)`;
  for (d) read `$ticket->getDueAt()` from the object returned by `createTicket()` and pass
  it as `$now` unchanged.
- The traffic simulator (`src/Command/SimulateTrafficCommand.php`) already hits
  `GET /api/tickets?status=...`; no change there is needed for this item.
