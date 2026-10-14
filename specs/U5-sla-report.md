# U5: GET /api/reports/sla per-priority totals, breached counts and breach rate

**Kind:** feature
**Evidence:** URS-F-05.1, URS-F-05.2, URS-F-05.3 (docs/urs/URS-002-sla-module.md, section 4; "breached" is defined in section 3)
**Constraints:** URS-N-02 (docs/urs/URS-002-sla-module.md, section 5); URS-N-03 is checked as preserved behaviour
**Depends on:** F0 (`dueAt` column, `createTicket()` factory), U4 (`TicketRepository::withBreached()`, the single breach predicate)

## Current behaviour

There is no `/api/reports/sla` route: `GET /api/reports/sla` falls through the router and
the existing `ApiExceptionListener` (`src/EventListener/ApiExceptionListener.php:18-40`)
renders the `NotFoundHttpException` as a JSON 404. The only report today is
`GET /api/stats`, served by `StatsController::__invoke()`
(`src/Controller/StatsController.php:14-18`) which returns `TicketStats::summary()`
(`src/Service/TicketStats.php:16-31`). That method counts by status only (no priority, no
breach) and computes `round($closed / $total * 100, 1)` at line 29 with no guard, so on an
empty table it throws `DivisionByZeroError` and the request is a 500; fixing it is a
separate item and the code must not be reused as-is. `TicketRepository`
(`src/Repository/TicketRepository.php`) already carries everything the breach side needs:
`withBreached(QueryBuilder $qb, bool $breached, \DateTimeImmutable $now)` (lines 52-65)
adds `t.status <> :closedStatus AND t.dueAt IS NOT NULL AND t.dueAt < :now` via `andWhere()`
with the date bound as `Types::DATETIME_IMMUTABLE`, and its docblock (lines 70-71) states
that U5 must count through it so the definition lives in one place. The repository has no
counting-by-priority method; `countByStatus()` (lines 90-93) uses `count(['status' => ...])`.
`Ticket::PRIORITIES` is `['low', 'normal', 'high']` (`src/Entity/Ticket.php:14`) and
`Ticket::$priority` is a plain `length: 16` string column (line 31); `POST /api/tickets`
(`src/Controller/TicketController.php:57-69`) does not validate it, and the traffic
simulator posts `priority: 'urgent'` (`src/Command/SimulateTrafficCommand.php:57`), so rows
whose priority is not in `Ticket::PRIORITIES` do exist in real databases.
`Ticket::toArray()` (lines 111-123) emits dates as `DATE_ATOM`. `AbstractController::json()`
(`vendor/symfony/framework-bundle/Controller/AbstractController.php:152-160`) serializes
with `JsonResponse::DEFAULT_ENCODING_OPTIONS`, which does not include
`JSON_PRESERVE_ZERO_FRACTION`, so a PHP float `0.0` is emitted as `0` unless the context
says otherwise (this is why `closed_ratio` comes back as the integer `0` today).

## Required behaviour

1. **Route (URS-F-05.1).** `GET /api/reports/sla` exists, needs no parameters and returns
   **200** with `Content-Type: application/json` and exactly this envelope, top-level keys
   in this order and no others:
   ```json
   {
     "generatedAt": "2026-10-14T10:00:00+00:00",
     "priorities": [
       {"priority": "low",    "total": 0, "breached": 0, "breach_rate": 0.0},
       {"priority": "normal", "total": 3, "breached": 1, "breach_rate": 33.3},
       {"priority": "high",   "total": 1, "breached": 1, "breach_rate": 100.0}
     ]
   }
   ```
   `generatedAt` is the evaluation instant `now` (see point 3) formatted with `DATE_ATOM`,
   the same format and timezone handling as `createdAt`/`dueAt` in `Ticket::toArray()`.
2. **Entry shape and order (URS-F-05.2).** `priorities` is a JSON array (a PHP list, never
   an object keyed by priority). It contains one entry per value of `Ticket::PRIORITIES`,
   **in that constant's order** (`low`, `normal`, `high`), each entry with exactly the keys
   `priority` (string), `total` (JSON integer), `breached` (JSON integer), `breach_rate`
   (JSON number) in that order. Every known priority is present even when its `total` is
   `0`; the list is never shorter than `count(Ticket::PRIORITIES)`.
3. **Definitions.** For one request a single `now` is taken once (`new \DateTimeImmutable()`
   when not pinned, see point 10) and used for every priority and for `generatedAt`.
   `total` is the number of tickets whose `priority` equals the entry's value, whatever
   their status. `breached` is the number of those tickets that satisfy the U4 definition
   at `now`: `status !== 'closed'` **and** `dueAt !== null` **and** `dueAt < now`, obtained
   through `TicketRepository::withBreached($qb, true, $now)` and never by restating the
   predicate in DQL, SQL or PHP. Consequently a `closed` ticket past its due time, a ticket
   with `dueAt === null` and a ticket whose `dueAt >= now` all count in `total` but not in
   `breached`, and `0 <= breached <= total` always holds.
4. **Breach rate (URS-F-05.1).** `breach_rate` is `breached / total * 100` rounded to one
   decimal with PHP `round($x, 1)` (half away from zero): 1 of 3 is `33.3`, 2 of 3 is
   `66.7`, 1 of 6 is `16.7`, 1 of 2 is `50.0`, 1 of 1 is `100.0`. It is always a PHP
   `float` and is **encoded as a JSON number with a fractional part** (`0.0`, `50.0`,
   `100.0`, never `0`, `50`, `"50.0"`): the response is built with
   `JSON_PRESERVE_ZERO_FRACTION` added to the encoding options, so `json_decode()` on the
   client side yields a float for every `breach_rate`.
5. **Zero total (URS-F-05.2).** When `total` is `0` the entry is
   `{"priority": "<p>", "total": 0, "breached": 0, "breach_rate": 0.0}`; no division is
   performed for that entry.
6. **Empty table (URS-F-05.3).** With no ticket rows at all the endpoint still returns
   **200** and the three zero entries of point 5 (`priorities` has exactly 3 elements). It
   never raises `DivisionByZeroError`, never returns 500 and never returns `null`/`NaN` for
   any value.
7. **Unknown priorities are counted under one extra trailing entry.** Tickets whose
   `priority` is not in `Ticket::PRIORITIES` (for example `urgent` from the simulator)
   are **not ignored**: they are aggregated, all together whatever their actual value, into
   one additional entry `{"priority": "other", "total": n, "breached": m, "breach_rate": r}`
   computed with the same rules as points 3-5 and appended **after** the known priorities,
   so it is always the last element. The `other` entry is present **only when its `total`
   is at least 1**; when every ticket has a known priority `priorities` has exactly
   `count(Ticket::PRIORITIES)` elements. The sum of all `total` values therefore always
   equals the number of rows in `ticket`, and the sum of all `breached` values equals
   `count` of `GET /api/tickets?breached=true` taken at the same instant.
8. **No input, so no client fault from the query string (URS-N-02).** The endpoint reads
   no query parameter and no body. Any query string, including values that other
   endpoints reject (`?breached=maybe`, `?status=done`) and non-scalar values
   (`?priority[]=low`, `?now[a]=1`), is ignored: the response is the same 200 JSON as a
   bare call (`priorities` identical; `generatedAt` may differ by the elapsed time). The
   controller must not call `InputBag::get()` on anything, must not throw and must not
   produce a 500 or an HTML page for any query string.
9. **Wrong method is a JSON 405 (URS-N-02).** `POST`, `PUT`, `PATCH` and `DELETE` on
   `/api/reports/sla` return **405** with `Content-Type: application/json`, a body with an
   `error` string key and an `Allow` header containing `GET`. No new code is needed for
   this: the route lives under `/api/` so the existing `ApiExceptionListener` renders the
   `MethodNotAllowedHttpException`; the route path must therefore stay under `/api/`.
10. **Repository: counting through the shared predicate.** `App\Repository\TicketRepository`
    gains exactly one method:
    `public function countByPriority(?bool $breached = null, ?\DateTimeImmutable $now = null): array`
    returning `array<string, int>`, a map `priority => count` built from a single grouped
    query (`SELECT t.priority, COUNT(t.id) ... GROUP BY t.priority`) on a fresh
    `createQueryBuilder('t')` (not `createNewestFirstQueryBuilder()`: no `ORDER BY` on a
    grouped query). When `$breached` is not null it applies
    `$this->withBreached($qb, $breached, $now ?? new \DateTimeImmutable())` before
    executing, exactly as `findNewestFirst()` does. Counts are cast to `int`. On an empty
    table, or when no row matches, it returns `[]` (an empty array, not null, no
    exception). Priorities absent from the table are simply absent from the map; filling
    in zeros is the service's job. `withBreached()`, `findNewestFirst()`,
    `createNewestFirstQueryBuilder()`, `withStatus()`, `findAllNewestFirst()` and
    `countByStatus()` keep their names, signatures and results.
11. **Service: a new `App\Service\SlaReport`.** The report is computed in a new, autowired
    service class `App\Service\SlaReport` with a constructor taking `TicketRepository` and
    exactly one public method `generate(?\DateTimeImmutable $now = null): array` returning
    the PHP array of point 1 (`generatedAt` string, `priorities` list). It calls
    `countByPriority()` once for totals and `countByPriority(true, $now)` once for breached
    counts (two queries per report, never one query per priority, never loading `Ticket`
    entities and filtering in PHP), then folds both maps over `Ticket::PRIORITIES` plus the
    `other` bucket of point 7. `$now` defaults to `new \DateTimeImmutable()` when null, is
    passed to the repository and is the value formatted into `generatedAt`, so tests can
    pin the clock at service level. `TicketStats` is neither modified nor called.
12. **Controller stays thin.** A new `App\Controller\SlaReportController` (invokable,
    `#[Route('/api/reports/sla', name: 'report_sla', methods: ['GET'])]`, extends
    `AbstractController`) takes `SlaReport` by autowiring, calls `generate()` with no
    argument and returns the result as JSON with `JSON_PRESERVE_ZERO_FRACTION` (point 4).
    It contains no counting, no rounding and no DQL. No logging is required for this
    item.
13. **Everything else is unchanged (URS-N-03).** `GET /api/stats` keeps exactly the keys
    `total, open, in_progress, closed, closed_ratio` and its current behaviour (including
    its empty-table bug, which is out of scope). `GET /api/tickets` (with and without
    `status`/`breached`), `POST /api/tickets`, `GET /api/tickets/{id}`,
    `PATCH /api/tickets/{id}/status`, `PATCH /api/tickets/{id}/assignee` and `/api/agents*`
    keep their status codes and JSON shapes; no key is added to or removed from the ticket
    JSON.

## Acceptance criteria

Each criterion is one test method in a new `tests/Api/U5SlaReportTest.php` extending
`ApiTestCase`, in the style of `tests/Api/U4BreachedFilterTest.php`: a private
`at(string $modifier): \DateTimeImmutable` helper, a private `nullDueAt(Ticket $ticket)`
helper that runs `UPDATE ticket SET due_at = NULL WHERE id = ?` and clears the entity
manager (the constructor always fills `dueAt`), `createTicket()` with named `priority`,
`status`, `createdAt`, `dueAt` arguments, `body()` for JSON. "T" is the test's wall clock;
`dueAt: T-1h` on a non-closed ticket means breached. `assertSame()` on `breach_rate` is
valid because point 4 guarantees `json_decode()` returns a float (`33.3`, `50.0`, `0.0`).
Every existing test (`TicketApiTest`, `F0SlaFoundationTest`, `R1FilterTicketsByStatusTest`,
`U1AgentsCreateListTest`, `U2AgentActiveFlagTest`, `U3TicketAssigneeTest`,
`U4BreachedFilterTest`, `tests/Unit/*`) must stay green unchanged.

- [ ] AC1 (URS-F-05.3) The empty table is a 200 with three zero entries, not a 500: with
      no ticket created, `GET /api/reports/sla` returns 200, `Content-Type` starts with
      `application/json`, `array_keys($body) === ['generatedAt', 'priorities']`,
      `\DateTimeImmutable::createFromFormat(DATE_ATOM, $body['generatedAt'])` is not
      `false`, `count($body['priorities']) === 3`, `array_column($body['priorities'],
      'priority') === ['low', 'normal', 'high']`, and every entry has
      `array_keys($entry) === ['priority', 'total', 'breached', 'breach_rate']`,
      `total === 0`, `breached === 0`, `is_float($entry['breach_rate'])` and
      `breach_rate === 0.0`.
- [ ] AC2 (URS-F-05.1) Totals, breached counts and rates are computed per priority: create
      `low` open (createdAt T-3h, dueAt T-1h, breached), `low` open (createdAt T-2h, dueAt
      T+1h), `normal` in_progress (createdAt T-4h, dueAt T-2h, breached), `normal` closed
      (createdAt T-3h, dueAt T-1h), `normal` open (createdAt T-1h, dueAt T+1h), `high` open
      (createdAt T-1h, dueAt T-30min, breached). `GET /api/reports/sla` returns 200 and
      `$body['priorities']` is exactly
      `[['priority' => 'low', 'total' => 2, 'breached' => 1, 'breach_rate' => 50.0],
      ['priority' => 'normal', 'total' => 3, 'breached' => 1, 'breach_rate' => 33.3],
      ['priority' => 'high', 'total' => 1, 'breached' => 1, 'breach_rate' => 100.0]]`
      (`assertSame` on the whole list), and `array_sum(array_column(..., 'total')) === 6`.
- [ ] AC3 (URS-F-05.1) The rate is rounded to one decimal and a non-zero total with no
      breach is `0.0`: create six `low` tickets (one open with dueAt T-1h, five open with
      dueAt T+1h), three `normal` tickets (two open with dueAt T-1h, one open with dueAt
      T+1h) and one `high` closed ticket with dueAt T-1h. `GET /api/reports/sla` returns
      200 and, by priority, `low` is `total 6, breached 1, breach_rate 16.7`, `normal` is
      `total 3, breached 2, breach_rate 66.7`, `high` is `total 1, breached 0,
      breach_rate 0.0` (`assertSame` on each of the three values; `is_float` on each
      `breach_rate`).
- [ ] AC4 (URS-F-05.2) A known priority with no ticket is still listed with a zero rate and
      no `other` entry appears when every priority is known: create one `high` open ticket
      with dueAt T-1h only. `GET /api/reports/sla` returns 200,
      `count($body['priorities']) === 3`, `priorities[0]` is exactly
      `['priority' => 'low', 'total' => 0, 'breached' => 0, 'breach_rate' => 0.0]`,
      `priorities[1]` is exactly `['priority' => 'normal', 'total' => 0, 'breached' => 0,
      'breach_rate' => 0.0]` and `priorities[2]` is exactly `['priority' => 'high',
      'total' => 1, 'breached' => 1, 'breach_rate' => 100.0]`.
- [ ] AC5 The breach definition is U4's: all four tickets `normal`: open (createdAt T-4h,
      dueAt T-2h, title `Breached`), closed (createdAt T-3h, dueAt T-1h, title `Closed past
      due`), open (createdAt T-2h, dueAt T+1h, title `Not yet due`), open (createdAt T-1h)
      then `nullDueAt()` (title `Legacy null due`). `GET /api/reports/sla` returns 200 and
      the `normal` entry is `total 4, breached 1, breach_rate 25.0`. Then
      `GET /api/tickets?breached=true` returns `count === 1` and `GET /api/tickets`
      returns `count === 4`, i.e. the report's `breached` and `total` for `normal` equal
      the U4 filter's counts for the same rows.
- [ ] AC6 Tickets with an unknown priority are aggregated under a trailing `other` entry:
      create `urgent` open (createdAt T-2h, dueAt T-1h, breached), `urgent` open
      (createdAt T-1h, dueAt T+1h), `critical` closed (createdAt T-1h, dueAt T-1h) and one
      `normal` open (createdAt T-1h, dueAt T+1h). `GET /api/reports/sla` returns 200,
      `count($body['priorities']) === 4`, `array_column($body['priorities'], 'priority')
      === ['low', 'normal', 'high', 'other']`, `priorities[3]` is exactly
      `['priority' => 'other', 'total' => 3, 'breached' => 1, 'breach_rate' => 33.3]`,
      `priorities[1]` is `total 1, breached 0, breach_rate 0.0`, and
      `array_sum(array_column($body['priorities'], 'total')) === 4`.
- [ ] AC7 (URS-N-02) Any query string is ignored and never produces a 500 or HTML: create
      one `normal` open ticket with dueAt T-1h; call `GET /api/reports/sla` and keep
      `$reference = $this->body()['priorities']`. Then for each of
      `?breached=maybe`, `?status=done`, `?priority[]=low`, `?now[a]=1`, `?now=garbage`,
      `GET /api/reports/sla<query>` returns 200, `Content-Type` starts with
      `application/json`, `body()` decodes without throwing,
      `array_keys($body) === ['generatedAt', 'priorities']` and
      `$body['priorities'] === $reference`.
- [ ] AC8 (URS-N-02) A wrong method is a JSON 405, not an HTML page and not a 500:
      `POST /api/reports/sla` via `postJson('/api/reports/sla', [])` returns 405,
      `Content-Type` starts with `application/json`, `body()` decodes without throwing and
      has a string `error` key, and the `Allow` response header contains `GET`.
      `$this->client->request('DELETE', '/api/reports/sla')` returns 405 with a JSON
      `Content-Type` as well.
- [ ] AC9 (URS-N-03) Existing endpoints are untouched: create one `low` open ticket with
      dueAt T-1h. `GET /api/stats` returns 200 with
      `array_keys($body) === ['total', 'open', 'in_progress', 'closed', 'closed_ratio']`,
      `total === 1` and `closed_ratio == 0` (`assertEquals`, as in `TicketApiTest`).
      `GET /api/tickets` returns 200 with `array_keys($body) === ['items', 'count']` and
      `array_keys($body['items'][0]) === ['id', 'title', 'description', 'priority',
      'status', 'createdAt', 'dueAt', 'assignee']`; `GET /api/tickets?breached=true`
      returns 200 with `count === 1`; `GET /api/tickets?breached=maybe` still returns 400
      with `error === 'Invalid breached'`; `POST /api/tickets` with a valid body still
      returns 201.
- [ ] AC10 The service and the repository count through the shared, clock-pinned
      predicate (service-level test using `static::getContainer()->get(SlaReport::class)`
      and `->get(TicketRepository::class)`, as U4 AC11 does): first, on the empty table,
      `$repository->countByPriority() === []` and
      `$repository->countByPriority(true, new \DateTimeImmutable()) === []`, and
      `$service->generate()['priorities']` has 3 entries all `total 0`. Then create one
      `normal` open ticket (createdAt T-1h, dueAt T+1h) and one `high` closed ticket
      (createdAt T-1h, dueAt T-2h). Assert:
      (a) `countByPriority() === ['normal' => 1, 'high' => 1]` (compare with
      `assertEqualsCanonicalizing` on keys and values; order of the map is not specified)
      and every value `is_int`;
      (b) `countByPriority(true, new \DateTimeImmutable()) === []` (nothing breached now);
      (c) `countByPriority(true, $this->at('+2 hours')) === ['normal' => 1]` (pinning the
      clock forward breaches the normal ticket; the closed high ticket stays unbreached);
      (d) `countByPriority(false, $this->at('+2 hours')) === ['high' => 1]`;
      (e) `$now = $this->at('+2 hours'); $report = $service->generate($now)` gives
      `$report['generatedAt'] === $now->format(DATE_ATOM)`, the `normal` entry
      `total 1, breached 1, breach_rate 100.0` and the `high` entry `total 1, breached 0,
      breach_rate 0.0`, while `$service->generate()` (unpinned) gives `normal` `breached 0,
      breach_rate 0.0`;
      (f) `$service->generate($now)['priorities']` is a list (`array_is_list`) whose
      `priority` column is exactly `['low', 'normal', 'high']`.

## Out of scope

- Do **not** fix, refactor, call or delete `TicketStats::summary()` or `StatsController`:
  the `/api/stats` division by zero is a separate item. AC9 only checks that `/api/stats`
  still answers with its five keys when a ticket exists; it is not called on an empty
  table by this item's tests.
- No change to `src/Entity/Ticket.php` (no `isBreached()`, no change to `PRIORITIES`,
  `STATUSES`, `toArray()`), no migration, no schema change.
- No priority validation on `POST /api/tickets` and no cleanup of existing unknown
  priorities: the report handles them through the `other` bucket (point 7). Do not add
  `other` to `Ticket::PRIORITIES`.
- No query parameters on the report: no `?status=`, `?priority=`, `?since=`, `?from=`,
  `?to=`, no `?now=` to override the clock over HTTP, no pagination. No `src/Dto/` class
  and no `#[MapQueryString]`/`#[MapQueryParameter]` (there is nothing to map).
- No per-agent, per-status or time-bucketed breakdown; no average time-to-close; no
  `breached` list inside the report (that is `GET /api/tickets?breached=true`).
- No change to `TicketRepository::withBreached()`, `findNewestFirst()`,
  `createNewestFirstQueryBuilder()`, `withStatus()`, `findAllNewestFirst()` or
  `countByStatus()`; no second breach predicate anywhere.
- No change to `TicketController`, `AgentController`, `src/EventListener/`,
  `src/Command/SimulateTrafficCommand.php` (no new simulator step), `src/Dto/`,
  `migrations/`, `config/`, `composer.json`, `tests/Api/ApiTestCase.php` or any existing
  test file.
- No `symfony/clock` installation and no `ClockInterface`: the clock is the optional
  `\DateTimeImmutable` argument of `SlaReport::generate()` and `countByPriority()`, with
  `new \DateTimeImmutable()` as the default, exactly as U4 did.
- No caching of the report, no logging, no new exception listener or
  `framework.exceptions` configuration (the existing listener already covers 404/405).

## Hints for the developer

- `countByPriority()`: `$qb = $this->createQueryBuilder('t')->select('t.priority AS priority, COUNT(t.id) AS total')->groupBy('t.priority');`
  then `if (null !== $breached) { $this->withBreached($qb, $breached, $now ?? new \DateTimeImmutable()); }`
  and fold `$qb->getQuery()->getArrayResult()` into `$map[$row['priority']] = (int) $row['total']`.
  `withBreached()` only calls `andWhere()`/`setParameter()`, so it stacks on a grouped
  builder without any change; keep the `Types::DATETIME_IMMUTABLE` binding it already does.
- `SlaReport::generate()`: `$now ??= new \DateTimeImmutable();`, `$totals =
  $this->tickets->countByPriority();`, `$breached = $this->tickets->countByPriority(true,
  $now);`. Loop over `Ticket::PRIORITIES` reading `$totals[$p] ?? 0` and `$breached[$p] ?? 0`;
  then sum the keys of `$totals` that are not in `Ticket::PRIORITIES` (and the matching
  `$breached` keys) into the `other` entry and append it only when its total is `> 0`.
  Rate: `0 === $total ? 0.0 : round($breached / $total * 100, 1)` (`round()` returns a
  float; keep the `0.0` literal, not `0`). `'generatedAt' => $now->format(DATE_ATOM)`.
- Controller: `return $this->json($this->report->generate(), context: ['json_encode_options' => JsonResponse::DEFAULT_ENCODING_OPTIONS | \JSON_PRESERVE_ZERO_FRACTION]);`
  `AbstractController::json()` merges the context into the Serializer call
  (`vendor/symfony/framework-bundle/Controller/AbstractController.php:152-160`), and
  `symfony/serializer` is installed so that branch is the one taken. Without the flag the
  serializer emits `0` and `50` and AC1/AC2 fail on `is_float`/`assertSame(50.0, ...)`.
  Alternatively build `new JsonResponse($data)` and call
  `$response->setEncodingOptions($response->getEncodingOptions() | \JSON_PRESERVE_ZERO_FRACTION)`.
- `composer.json` already has `symfony/property-access` and `symfony/property-info`, but
  this item maps no request payload, so nothing needs installing.
- Keep the route under `/api/` so `ApiExceptionListener` renders the 405 of AC8; a
  `#[Route('/api/reports/sla', ...)]` attribute on an invokable controller is enough,
  routing is attribute-only (`config/routes.yaml` imports `routing.controllers`).
- For AC10 fetch the services from the container exactly as `U4BreachedFilterTest` AC11
  does for `TicketRepository`; `SlaReport` is autowired and public enough for
  `static::getContainer()->get(SlaReport::class)` in the test environment.
