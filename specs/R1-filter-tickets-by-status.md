# R1: Filter GET /api/tickets by status query parameter

**Kind:** feature
**Evidence:** requests/001-filter-tickets-by-status.md
**Constraints:** URS-N-02, URS-N-03 (docs/urs/URS-002-sla-module.md, section 5)

## Current behaviour

`GET /api/tickets` is served by `TicketController::list()`
(`src/Controller/TicketController.php:27-33`). The action takes no `Request` argument and
calls `$this->tickets->findAllNewestFirst()` unconditionally (line 30), then wraps the
result as `{"items": [...], "count": n}` (line 32). `TicketRepository::findAllNewestFirst()`
(`src/Repository/TicketRepository.php:19-23`) is a plain `findBy([], ['createdAt' => 'DESC',
'id' => 'DESC'])` with no criteria and no query builder, so there is nothing to compose a
filter onto. As a result `GET /api/tickets?status=open` returns every ticket regardless of
status, and `?status=anything` is silently accepted: the traffic simulator already sends
that exact request (`src/Command/SimulateTrafficCommand.php:63`) and gets the full list.
The allowed statuses are `Ticket::STATUSES = ['open', 'in_progress', 'closed']`
(`src/Entity/Ticket.php:15`). The only existing validation of a status value lives in
`TicketController::changeStatus()` (lines 67-69), which answers
`{"error": "Invalid status", "allowed": [...]}` with 422 for a bad PATCH body; the list
endpoint has no equivalent for its query string.

## Required behaviour

1. `GET /api/tickets` **without** a `status` query parameter keeps returning every ticket,
   newest first (`createdAt` DESC, then `id` DESC), as `{"items": [...], "count": n}`.
   Status code, envelope keys, item keys and item values are unchanged (URS-N-03).
2. `GET /api/tickets?status=<value>` where `<value>` is one of `Ticket::STATUSES`
   (`open`, `in_progress`, `closed`, compared case-sensitively and exactly) returns 200
   with `items` containing only the tickets whose `status` equals `<value>`, in the same
   newest-first order as point 1, and `count` equal to `count(items)`. When no ticket
   matches, the response is 200 `{"items": [], "count": 0}`, not an error.
3. `GET /api/tickets?status=<value>` where `<value>` is present but **not** one of
   `Ticket::STATUSES` returns **400** with `Content-Type: application/json` and exactly the
   body `{"error": "Invalid status", "allowed": ["open", "in_progress", "closed"]}`
   (`allowed` is `Ticket::STATUSES` in its declared order, so a new status added to the
   constant appears automatically). "Present but invalid" includes the empty string
   (`?status=`), a different case (`?status=Open`, `?status=OPEN`), surrounding whitespace
   (`?status=%20open`) and typos (`?status=done`, `?status=opne`). The ticket list is not
   queried in this case.
4. (URS-N-02) A non-scalar `status` value, `?status[]=open` or `?status[a]=open`, also
   returns the 400 JSON body of point 3; it must never produce a 500 or an HTML error page.
   Note that `Request::$query->get('status')` throws on array input and the kernel would
   render that as an HTML 400, which violates URS-N-02.
5. (URS-N-03) The per-ticket JSON object stays `Ticket::toArray()` with the key set
   `id, title, description, priority, status, createdAt, dueAt, assignee`, in that order,
   both with and without the filter. The envelope stays exactly `items` and `count`.
   `POST /api/tickets`, `GET /api/tickets/{id}`, `PATCH /api/tickets/{id}/status` and
   `GET /api/stats` are untouched.
6. **Repository design (composability for URS-F-04.3).** The filtering lives in
   `App\Repository\TicketRepository` as Doctrine `QueryBuilder` pieces, not as a `findBy()`
   call and not as PHP-side `array_filter` in the controller:
   1. `public function createNewestFirstQueryBuilder(): \Doctrine\ORM\QueryBuilder` returns
      a builder on alias `t` selecting `Ticket` with `ORDER BY t.createdAt DESC, t.id DESC`
      and no `WHERE` clause.
   2. `public function withStatus(\Doctrine\ORM\QueryBuilder $qb, string $status): \Doctrine\ORM\QueryBuilder`
      adds `AND t.status = :status` (via `andWhere` + `setParameter`, so it can be stacked
      after other `andWhere` calls) and returns the same builder. It does not validate
      `$status`; validation is the controller's job (point 3).
   3. `public function findNewestFirst(?string $status = null): array` (`list<Ticket>`)
      builds `createNewestFirstQueryBuilder()`, applies `withStatus()` only when `$status`
      is not null, and returns `getQuery()->getResult()`. This is the method `list()`
      calls. The backlog item URS-F-04.3 (breach filter, "combinable with any status
      filter") will add a sibling `withBreached(QueryBuilder, bool, \DateTimeImmutable)`
      and a second optional parameter on `findNewestFirst()`; the developer must leave
      room for that (no `setMaxResults`, no `select` override, no `where()` that would
      replace an earlier clause).
   4. `findAllNewestFirst()` keeps its name, signature and result (it may delegate to
      `findNewestFirst()`); `countByStatus()` is unchanged.
7. The controller stays thin: `list()` reads the `status` query parameter, validates it
   against `Ticket::STATUSES`, returns the 400 of point 3 on failure, otherwise delegates
   to `findNewestFirst($status)` and maps with `toArray()` as today. No logging change is
   required for this item.

## Acceptance criteria

Each criterion is one test method in a new `tests/Api/R1FilterTicketsByStatusTest.php`
extending `ApiTestCase` (use `createTicket()` with explicit `createdAt` values so the
newest-first assertions are deterministic, and `body()` for JSON). The three existing
tests in `tests/Api/TicketApiTest.php` and all of `tests/Api/F0SlaFoundationTest.php` must
stay green unchanged.

- [ ] AC1 Without the parameter, behaviour is unchanged: create three tickets with
      statuses `open` (createdAt T-3h), `in_progress` (T-2h), `closed` (T-1h); `GET
      /api/tickets` returns 200, `count === 3`, and `items[*].status` is
      `['closed', 'in_progress', 'open']` (newest first).
- [ ] AC2 `?status=open` returns only open tickets, newest first: create `open` (T-3h),
      `closed` (T-2h), `open` (T-1h, title `Newest open`), `in_progress` (T-30min); `GET
      /api/tickets?status=open` returns 200, `count === 2`, `items[0]['title'] ===
      'Newest open'`, and every `items[*]['status'] === 'open'`.
- [ ] AC3 Every known status filters correctly: create one ticket per value of
      `Ticket::STATUSES`; for each value `v`, `GET /api/tickets?status=v` returns 200,
      `count === 1` and `items[0]['status'] === v`.
- [ ] AC4 A valid status with no match is an empty list, not an error: create one `open`
      ticket; `GET /api/tickets?status=closed` returns 200 and the body is exactly
      `['items' => [], 'count' => 0]`.
- [ ] AC5 An unknown status is a 400 naming the allowed values: create one `open` ticket;
      `GET /api/tickets?status=done` returns 400, the response `Content-Type` header
      starts with `application/json`, and the body is exactly
      `['error' => 'Invalid status', 'allowed' => ['open', 'in_progress', 'closed']]`.
- [ ] AC6 (URS-N-02) Edge values are rejected the same way, never 500: for each of
      `?status=`, `?status=Open`, `?status=OPEN`, `?status=%20open`, `?status=opne`,
      `GET /api/tickets` returns 400 with a JSON body whose `error` key is
      `'Invalid status'` and whose `allowed` key equals `Ticket::STATUSES`.
- [ ] AC7 (URS-N-02) A non-scalar value is a JSON 400, not a 500 and not HTML:
      `GET /api/tickets?status[]=open` and `GET /api/tickets?status[a]=open` both return
      400 with `Content-Type` starting with `application/json` and the same body as AC5
      (`body()` must decode without throwing).
- [ ] AC8 (URS-N-03) The JSON shape is unchanged with and without the filter: create one
      `open` ticket; `GET /api/tickets` and `GET /api/tickets?status=open` both return 200
      with `array_keys($body) === ['items', 'count']` and
      `array_keys($body['items'][0]) === ['id', 'title', 'description', 'priority',
      'status', 'createdAt', 'dueAt', 'assignee']`; `items[0]` is identical in both
      responses.
- [ ] AC9 (URS-N-03) Other ticket endpoints are untouched by the change:
      `POST /api/tickets` still returns 201, `GET /api/tickets/{id}` 200, `PATCH
      /api/tickets/{id}/status` with `{"status": "done"}` still returns 422 (not 400) with
      `error === 'Invalid status'`, and `GET /api/stats` still returns exactly the keys
      `total, open, in_progress, closed, closed_ratio`.
- [ ] AC10 The repository exposes composable query-builder pieces (service-level test on
      `TicketRepository` from the container): `createNewestFirstQueryBuilder()` returns a
      `Doctrine\ORM\QueryBuilder`; after `withStatus($qb, 'open')` the builder's
      `getDQL()` contains `t.status = :status`; stacking a second
      `$qb->andWhere('t.priority = :p')->setParameter('p', 'high')` on the same builder
      and executing it returns only the open, high-priority tickets, newest first (set up
      with four tickets: open/high T-3h, open/low T-2h, closed/high T-1h, open/high
      T-30min; expect two results, the T-30min one first). `findNewestFirst()` with no
      argument returns the same list as `findAllNewestFirst()`.

## Out of scope

- No `breached` query parameter, no `withBreached()`, no breach computation: that is
  URS-F-04 (a later item). This item only has to leave the query builder composable.
- No pagination, no other filters (`priority`, `assignee`), no sorting parameter.
- Do not change the error handling or status codes of `PATCH /api/tickets/{id}/status`
  (its 422 stays 422), `GET /api/tickets/{id}` (the pre-existing 500 on an unknown id is
  not this item's concern), `POST /api/tickets`, or `GET /api/stats`.
- Do not touch `Ticket::toArray()`, `Ticket::STATUSES`, `src/Entity/`, `src/Service/`,
  `src/Command/SimulateTrafficCommand.php`, `migrations/`, `config/`, `composer.json`,
  `tests/Api/ApiTestCase.php`, `tests/Api/TicketApiTest.php`,
  `tests/Api/F0SlaFoundationTest.php`. No new migration (no schema change).
- Do not remove or rename `findAllNewestFirst()` or `countByStatus()`.
- Do not add a global exception listener or change `framework.exceptions` /
  error-renderer configuration to turn HTML errors into JSON project-wide; produce the
  JSON 400 in the controller for this one parameter.
- Do not add a `src/Dto/` class for a single optional scalar parameter.

## Hints for the developer

- Simplest fit: give `list()` a `Request $request` argument and read the raw query value
  with something array-tolerant (`$request->query->all()['status'] ?? null`), treat any
  non-string as invalid, then `\in_array($status, Ticket::STATUSES, true)` exactly as
  `changeStatus()` does, returning `$this->json([...], Response::HTTP_BAD_REQUEST)`.
  Beware `InputBag::get()` throws `BadRequestException` on array input, and
  `#[MapQueryParameter]` (`vendor/symfony/http-kernel/Attribute/MapQueryParameter.php:42`)
  defaults `validationFailedStatusCode` to 404 and throws `HttpException`, which the
  default error renderer turns into an HTML page for this request. Either is fine only if
  the developer still ends with the JSON body of Required behaviour 3 for every case in
  AC6 and AC7.
- Repository: `$this->createQueryBuilder('t')->orderBy('t.createdAt', 'DESC')->addOrderBy('t.id', 'DESC')`
  for the base builder; `$qb->andWhere('t.status = :status')->setParameter('status', $status)`
  for the filter. Keep `andWhere` (not `where`) so URS-F-04.3 can stack a breach clause.
- Tests: `$this->client->request('GET', '/api/tickets?status=open')` is enough; for AC7
  pass the query string literally (`'/api/tickets?status[]=open'`). Read the header with
  `$this->client->getResponse()->headers->get('Content-Type')`. For AC10 fetch the
  repository with `static::getContainer()->get(TicketRepository::class)` inside the same
  `ApiTestCase` subclass (the schema is already reset in `setUp()`).
