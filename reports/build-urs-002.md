# Build URS-002 - 2026-10-14 (in progress)

Run by hand through the roles to validate the `/build` path. Items U1 to FX remain; run
`/build docs/urs/URS-002-sla-module.md --resume` to continue.

| Item | Title | URS ids | Result | Fix rounds | Commit |
|------|-------|---------|--------|-----------|--------|
| F0 | Agent entity, Ticket assignee + dueAt, SLA constants, migration, ApiTestCase | F-01.1, F-02.1, F-03.1..4, N-03 | PASS | 1 | see `git log` on `build/urs-002` |
| U1 | POST/GET /api/agents | F-01.2, F-01.3 | pending | | |
| U2 | PATCH /api/agents/{id} active flag | F-01.4 | pending | | |
| U3 | PATCH /api/tickets/{id}/assignee | F-02.2..4 | pending | | |
| U4 | GET /api/tickets?breached= | F-04.1..3 | pending | | |
| U5 | GET /api/reports/sla | F-05.1..3 | pending | | |
| FX | URS-N acceptance sweep | N-01..03 | pending | | |

Role cost for F0, approximate output tokens:

| Role | Tokens | Tool calls | Wall time |
|------|--------|-----------|-----------|
| urs-decomposer | 84k | 5 | 125 s |
| spec-writer | 102k | 11 | 270 s |
| developer | 85k | 16 | 150 s |
| tester | 90k + 96k (fix round) | 11 + 6 | 220 s + 56 s |
| verifier x2 | 105k + 103k | 14 + 15 | 189 s + 124 s |

Suite before: OK (3 tests, 10 assertions). After: OK (18 tests, 160 assertions).

## Traceability

See section 7 of `docs/urs/URS-002-sla-module.md`.

## Open points for the human

- Round 1 FAIL was an orchestration mistake, not an agent mistake: the spec required the
  existing `TicketApiTest` to move onto the new base class, and the orchestrator's prompt
  kept the developer out of `tests/` while the tester was told not to restructure
  existing classes. Nobody owned the criterion. The `/improve` skill now states the split
  explicitly in step 2b.
- The verifier's mandated traffic replay rewrites `var/data_dev.db`. The tester's first
  migration test depended on that file's contents and was therefore flaky; it now builds
  its own fixture. Rule of thumb for future specs: a test must never read a database the
  verifier is allowed to reset.
- Decomposer flagged that U4 (breach filter combinable with status filter) depends on
  `requests/001`, which is not in this URS. Decide before U4: ship request 001 first via
  `/improve`, or accept U4 with the combination tested only against "no status filter".
