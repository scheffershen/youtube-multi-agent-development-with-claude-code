# R2: Add the urgent priority with a 2-hour SLA target (request 002)

**Kind:** feature
**Evidence:** requests/002-urgent-priority.md, URS-F-03.2 (docs/urs/URS-002-sla-module.md, section 4; URS-F-03.1 gives the existing targets, URS-F-03.3 fixes stored due times, URS-F-05.2 requires every known priority in the report)
**Constraints:** URS-N-02, URS-N-03 (docs/urs/URS-002-sla-module.md, section 5)
**Depends on:** F0 (`dueAt`, `Ticket::slaTargetHours()`, `createTicket()` factory), U5 (`GET /api/reports/sla` rows per `Ticket::PRIORITIES`), FX (`priority` validation through `CreateTicketRequest`)
**DECISIONS:** D1=B (2 hours), D2=A. D1: urgent is added with an SLA target of 2 hours (`approvals/approved/decision-R2-D1.md`). D2: `urgent` is appended after `high`, `Ticket::PRIORITIES = ['low', 'normal', 'high', 'urgent']`, report rows low, normal, high, urgent, then `other` when present (`approvals/approved/decision-R2-D2.md`). Both logged in `reports/items/R2/decisions.md`.

## Current behaviour

`Ticket::PRIORITIES` is `['low', 'normal', 'high']` and `Ticket::SLA_TARGET_HOURS` is
`['low' => 72, 'normal' => 24, 'high' => 8]` (`src/Entity/Ticket.php:14,16`);
`Ticket::slaTargetHours()` (lines 55-58) returns the `normal` target (24) for any other
string, and the constructor (line 52) derives `dueAt` from it when no explicit `dueAt` is
given. `POST /api/tickets` binds `CreateTicketRequest` with `#[MapRequestPayload]`
(`src/Controller/TicketController.php:58-61`); its `priority` carries
`#[Assert\Choice(choices: Ticket::PRIORITIES)]` (`src/Dto/CreateTicketRequest.php:22-24`), so
`"priority": "urgent"` is a **422** with body `{"errors": {"priority": "..."}}` rendered by
`ApiExceptionListener` (`src/EventListener/ApiExceptionListener.php:29-34`) and nothing is
stored. `SlaReport::generate()` (`src/Service/SlaReport.php:34-48`) emits one row per value of
`Ticket::PRIORITIES` in that order, then a trailing `other` row aggregating every stored
priority outside the list, only when its total is above zero; totals come from
`TicketRepository::countByPriority()` (`src/Repository/TicketRepository.php:103-117`), which
groups by the stored `priority` string. The standard traffic scenario
(`src/Command/SimulateTrafficCommand.php:57`) posts `priority: 'urgent'` as its fifth
request, today a 422, so the console summary reads `HTTP 422: 3` and the URS section 6
replay recorded `201: 2, 422: 3`. Rows with priority `urgent` already exist in real
databases: those migrated by F0 have `due_at` NULL
(`tests/Unit/F0SlaFoundationMigrationTest.php:43-71`), those created between F0 and FX have
the `normal` target (24 h), as `docs/user/tickets.md:42-45` documents. Four shipped tests
pin the current behaviour and are listed under Required behaviour 8.

## Required behaviour

1. **Accepted values (request 002, D1, D2).** `Ticket::PRIORITIES` is exactly
   `['low', 'normal', 'high', 'urgent']`: `urgent` appended after `high` (D2), ascending
   severity as today, the three existing values keeping their positions.
   `POST /api/tickets` with `{"title": "...", "description": "...", "priority": "urgent"}`
   returns **201** with the usual ticket JSON: the eight keys `id`, `title`, `description`,
   `priority`, `status`, `createdAt`, `dueAt`, `assignee` in that order, `priority` equal to
   `"urgent"`, `status` `"open"`, `assignee` `null`. `CreateTicketRequest` keeps
   `#[Assert\Choice(choices: Ticket::PRIORITIES)]`, so acceptance follows from the constant;
   the DTO itself does not change.
2. **SLA target (D1, URS-F-03.1).** `Ticket::SLA_TARGET_HOURS` gains `'urgent' => 2`;
   `Ticket::slaTargetHours('urgent')` returns `2`. A ticket created through the API with
   priority `urgent` gets `dueAt` equal to `createdAt` plus exactly 7200 seconds, both
   emitted as `DATE_ATOM` as today. Because D1 states a target of its own, URS-F-03.2's
   `normal` default does **not** apply to `urgent`.
3. **Fallback preserved (URS-F-03.2).** `Ticket::SLA_DEFAULT_PRIORITY` stays `'normal'` and
   `Ticket::slaTargetHours()` still returns `24` for every string outside the four values
   (`''`, `'LOW'`, `'Urgent'`, `'critical'`). No other target changes: low 72, normal 24,
   high 8.
4. **Rejection of everything else (request 002, URS-N-02).** `POST /api/tickets` with a
   `priority` that is not exactly one of the four values returns **422**,
   `Content-Type: application/json`, body exactly `{"errors": {"priority": "<non-empty string>"}}`
   when title and description are valid, and nothing is stored. The comparison is
   case-sensitive: `Urgent`, `URGENT`, `High` are rejected, as are `critical`, `""`, `null`,
   `123` and a missing key (same status and body shape as today). No request on this
   endpoint produces a 500.
5. **SLA report (URS-F-05.1, URS-F-05.2).** `GET /api/reports/sla` returns **200** with the
   unchanged envelope `{"generatedAt": ..., "priorities": [...]}` and one row
   `{"priority": "urgent", "total": n, "breached": m, "breach_rate": r}` with the four keys in
   that order, present even when `total` is `0` (then `breached` `0`, `breach_rate` `0.0`),
   computed by the same rules as the other rows (breached = not closed and `dueAt` past;
   rate rounded to one decimal). Rows for `low`, `normal`, `high` keep their values. The
   `urgent` row comes fourth, after `high` (D2), so `array_column($priorities, 'priority')`
   is `['low', 'normal', 'high', 'urgent']`, and the trailing `other` row still exists for
   stored priorities outside the four values, only when its total is above zero, always
   last (then the list is `['low', 'normal', 'high', 'urgent', 'other']`).
   `SlaReport` keeps iterating `Ticket::PRIORITIES`; no row is hard-coded.
6. **Existing rows: no retroactive change (URS-F-03.3).** Tickets stored with priority
   `urgent` before this change keep their stored `dueAt` exactly as it is in the column
   (`null` for rows migrated by F0, `createdAt` + 24 h for rows created between F0 and FX);
   nothing recomputes, backfills or rewrites `dueAt`, and no Doctrine migration is added
   (the `priority` column is `length: 16`, `urgent` fits; the schema does not change).
   Such rows are counted in the `urgent` row of the report, not in `other`, because the
   report groups by the stored priority string and `urgent` is now a known priority; a
   row with `dueAt` `null` is never breached (U4 predicate), and `GET /api/tickets`,
   `GET /api/tickets/{id}` keep returning them with their original priority.
7. **Backward compatibility (URS-N-03).** Every existing endpoint keeps its JSON shape:
   the eight ticket keys on `POST`, `GET` list, `GET /{id}`, `PATCH /{id}/status`,
   `PATCH /{id}/assignee`; the five stats keys on `GET /api/stats`; the two report envelope
   keys and the four row keys on `GET /api/reports/sla`. `GET /api/tickets?status=...` and
   `?breached=...` work unchanged for urgent tickets (an open urgent ticket whose `dueAt` is
   past appears under `breached=true`). `tests/Api/TicketApiTest.php` passes unchanged.
   The standard traffic scenario (`app:simulate-traffic --reset`) now yields
   `HTTP 200: 6`, `HTTP 201: 3`, `HTTP 404: 1`, `HTTP 422: 2`, no 5xx, and the fifth request
   logs `Ticket created` with `priority` `urgent` at level info as every creation does.
8. **Existing tests amended by this item (tester).** D1 reverses the FX decision that kept
   `urgent` rejected, so the shipped assertions that pinned that choice are wrong by
   decision, not by accident. The **tester** amends them as part of this item; the
   developer does not touch `tests/`. Exactly these, and nothing else in existing test files:
   - `tests/Api/FXUrsNAcceptanceSweepTest.php::testAc2UrsN02UnknownPriorityReturns422AndUrgentIsNotAdded`
     (lines 119-133): `'urgent'` leaves the rejected list at line 121 and line 132 becomes
     `self::assertSame(['low', 'normal', 'high', 'urgent'], Ticket::PRIORITIES)`; the method
     may be renamed to match (internal naming).
   - `tests/Api/FXUrsNAcceptanceSweepTest.php` line 53 (`EXPECTED_STATUSES`, used by
     `testAc10Section6StandardScenarioReplayProducesNo500`): index 3 becomes `201`.
   - `tests/Api/FXUrsNAcceptanceSweepTest.php::testAc11Section6ConsoleCommandReportsNo500`
     (line 367): `HTTP 422: 3` becomes `HTTP 422: 2`, and `HTTP 201: 3` is asserted the same
     way. The docblocks of these methods are updated to say what they now check.
   - `tests/Api/F0SlaFoundationTest.php::testAc11UrsF0302UnknownPriorityFallsBackToNormalTarget`
     (lines 210-220): lines 212-215 become "POST `urgent` returns 201 and `dueAt` is
     `createdAt` + 7200 s", line 217 becomes `self::assertSame(2, Ticket::slaTargetHours('urgent'))`,
     lines 218-219 stay (`''` and `'LOW'` still 24). Docblock updated accordingly.
   - `tests/Api/U5SlaReportTest.php::testAc6UnknownPrioritiesAreAggregatedUnderTrailingOtherEntry`
     (lines 162-176) and `KNOWN_PRIORITIES` (line 19): the two `urgent` fixtures become a
     priority outside the four values (for example `escalated`) so the test still exercises
     the `other` bucket; `KNOWN_PRIORITIES` becomes `['low', 'normal', 'high', 'urgent']` and
     the expected row list at line 174 becomes `['low', 'normal', 'high', 'urgent', 'other']`;
     the `other` row is then `$priorities[4]` and `assertCount(5, ...)`.
   Any other existing test that fails after the change is a defect in the implementation,
   not a candidate for amendment.

## Acceptance criteria

New class `tests/Api/R2UrgentPriorityTest.php` extending `ApiTestCase`, one method per
criterion, named `testAc<n>...` with the URS id in the name where one is tagged.

- [ ] AC1 (URS-F-03.1, D1) `postJson('/api/tickets', ['title' => 'Payment double charged', 'description' => 'Customer #4411 charged twice.', 'priority' => 'urgent'])`
      returns 201 with `Content-Type` starting `application/json`; `array_keys` of the body
      are exactly the eight ticket keys; `priority === 'urgent'`, `status === 'open'`,
      `assignee === null`; `new \DateTimeImmutable($body['dueAt'])` minus
      `new \DateTimeImmutable($body['createdAt'])` is exactly 7200 seconds.
- [ ] AC2 (D1) `Ticket::slaTargetHours('urgent') === 2`, `Ticket::SLA_TARGET_HOURS['urgent'] === 2`,
      and `Ticket::PRIORITIES === ['low', 'normal', 'high', 'urgent']` (D2);
      `Ticket::SLA_TARGET_HOURS` for `low`, `normal`, `high` is still 72, 24, 8.
- [ ] AC3 (URS-F-03.2) `Ticket::slaTargetHours()` returns 24 for each of `''`, `'LOW'`,
      `'Urgent'`, `'URGENT'`, `'critical'`; `Ticket::SLA_DEFAULT_PRIORITY === 'normal'`.
- [ ] AC4 (URS-N-02) For each `priority` value in `['Urgent', 'URGENT', 'High', 'critical', '', null, 123]`
      and for a body without the `priority` key, `postJson('/api/tickets', ...)` with a valid
      title and description returns 422, JSON content type, `array_keys($body) === ['errors']`,
      `array_keys($body['errors']) === ['priority']`, and the message is a non-empty string;
      afterwards `GET /api/tickets` reports `count === 0`.
- [ ] AC5 (URS-F-05.2) On an empty table, `GET /api/reports/sla` returns 200,
      `array_keys($body) === ['generatedAt', 'priorities']`, `count($body['priorities']) === 4`,
      `array_column($priorities, 'priority') === ['low', 'normal', 'high', 'urgent']` (D2) with
      no `other` row, and `$priorities[3]` (the `urgent` row) is exactly
      `['priority' => 'urgent', 'total' => 0, 'breached' => 0, 'breach_rate' => 0.0]`
      (compare with `assertSame` on keys and `assertEquals` on `breach_rate`, as U5 does).
- [ ] AC6 (URS-F-05.1) With `createTicket(priority: 'urgent', status: 'open', createdAt: -3 h, dueAt: -1 h)`,
      `createTicket(priority: 'urgent', status: 'open', createdAt: -1 h, dueAt: +1 h)`,
      `createTicket(priority: 'urgent', status: 'closed', createdAt: -3 h, dueAt: -1 h)` and
      `createTicket(priority: 'escalated', status: 'open', createdAt: -3 h, dueAt: -1 h)`,
      `GET /api/reports/sla` returns 200 with
      `array_column($priorities, 'priority') === ['low', 'normal', 'high', 'urgent', 'other']` (D2),
      `$priorities[3]` (the `urgent` row) with `total === 3`, `breached === 1`,
      `breach_rate == 33.3`, the `low`, `normal`, `high` rows all zero, and `$priorities[4]`
      exactly `['priority' => 'other', 'total' => 1, 'breached' => 1, 'breach_rate' => 100.0]`;
      `GET /api/tickets?breached=true` returns `count === 2`.
- [ ] AC7 (URS-N-03) After AC1's POST (id `$id`): `GET /api/tickets` returns 200 with
      `count === 1` and `items[0]['priority'] === 'urgent'`; `GET /api/tickets/{$id}`,
      `PATCH /api/tickets/{$id}/status` with `['status' => 'in_progress']` and
      `PATCH /api/tickets/{$id}/assignee` with `['agentId' => null]` each return 200 with
      `array_keys` exactly the eight ticket keys and `priority === 'urgent'`;
      `GET /api/tickets?status=in_progress` returns `count === 1`; `GET /api/stats` returns
      200 with `array_keys` exactly `['total', 'open', 'in_progress', 'closed', 'closed_ratio']`
      and `total === 1`.
- [ ] AC8 (URS-F-03.3) `$t0 = new \DateTimeImmutable('2026-02-01T09:00:00+00:00')`;
      `createTicket(priority: 'urgent', createdAt: $t0, dueAt: $t0->modify('+24 hours'))`
      is the shape of a row created between F0 and FX; a row migrated by F0 has `due_at`
      NULL and cannot come from the factory (the constructor derives `dueAt`), so insert it
      with `$this->em()->getConnection()->insert('ticket', [...])` (`priority` `urgent`,
      `status` `open`, `due_at` NULL, `assignee_id` NULL). After `flush()`/`clear()`,
      `find()` returns `dueAt` equal to `$t0 + 24 h` for the first and `null` for the second,
      neither equal to `$t0 + 2 h`; `GET /api/reports/sla` shows the `urgent` row
      `total === 2`, `breached === 1` (the 24 h one is past at test time; the `null` one is
      never breached) and no `other` row; `GET /api/tickets` returns both with
      `priority === 'urgent'`.
- [ ] AC9 (section 6, URS-N-02) Replaying the twelve-request standard scenario through the
      test client (same list as `FXUrsNAcceptanceSweepTest::SCENARIO`) yields exactly
      `[200, 201, 201, 422, 201, 200, 200, 404, 200, 422, 200, 200]`, every response JSON;
      the fifth response has `priority === 'urgent'`; running `app:simulate-traffic --reset`
      through `CommandTester` exits 0 and its display matches `/^HTTP 201: 3$/m` and
      `/^HTTP 422: 2$/m` and never `/-> 5\d\d$/m`.
- [ ] AC10 (URS-N-03) Amend the four shipped tests exactly as Required behaviour 8 lists
      (FX AC2 list and `PRIORITIES` assertion, FX `EXPECTED_STATUSES` index 3, FX AC11
      console counts, F0 AC11 lines 212-217, U5 AC6 fixtures, `KNOWN_PRIORITIES` and row
      list). `tests/Api/TicketApiTest.php` is byte-identical to its current content and the
      full suite (`tools/php vendor/bin/phpunit`) passes.

## Out of scope

- No change to `src/Dto/CreateTicketRequest.php` (the `Choice` constraint reads the constant),
  `src/Service/SlaReport.php` (iterates the constant; its docblock may mention `urgent`),
  `src/Repository/TicketRepository.php`, `src/Controller/*`, `src/EventListener/*`,
  `src/Command/SimulateTrafficCommand.php` (the scenario stays as it is; only its outcome
  changes), `src/Service/TicketStats.php`.
- No Doctrine migration, no backfill, no rewrite of existing `dueAt` values (Required
  behaviour 6). Existing rows with priorities outside the four values stay in `other`.
- No endpoint to change a ticket's priority; no change to `STATUSES`, to
  `PATCH /api/tickets/{id}/status` bodies, to the agents endpoints, to the breach filter.
- No change to `SLA_DEFAULT_PRIORITY` or to the low/normal/high targets.
- End-user documentation (`docs/user/tickets.md` lines 19, 33-40, 85, 110 and
  `docs/user/statistiques.md` lines 41-44, 54, 89, 94-99 list the priorities and row order)
  is handled by the publish step (doc-writer), not by this item. The URS section 7
  traceability row for URS-F-03 and the section 6 replay figures (`201: 3, 422: 2` after this
  item) are updated by the orchestrator. Moving `requests/002-urgent-priority.md` to
  `requests/done/` is the orchestrator's step after shipping.
- `tests/Unit/F0SlaFoundationMigrationTest.php` is untouched: it asserts `due_at` NULL after
  migration and never reads the targets.

## Hints for the developer

- The production diff is two constants in `src/Entity/Ticket.php`: append `'urgent'` to
  `PRIORITIES` after `'high'` (D2) and add `'urgent' => 2` to `SLA_TARGET_HOURS`. Nothing else
  needs code: the `Choice` constraint, `slaTargetHours()`, the constructor and `SlaReport`
  all read those constants. If anything else seems necessary, re-read Required behaviour 6
  and 7 before adding it.
- `symfony/property-access` and `symfony/property-info` are already in `composer.json`
  (lines 18-19); `#[MapRequestPayload]` keeps working, no install needed.
- Run `tools/php vendor/bin/phpunit` after the change: before the tester's amendments land,
  exactly the four tests in Required behaviour 8 fail and nothing else; if another test
  fails, the change went too far.
