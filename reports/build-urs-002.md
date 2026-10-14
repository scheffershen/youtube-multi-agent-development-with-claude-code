# Build URS-002 - 2026-10-14 (complete)

Run through the ten roles on branch `build/urs-002`: urs-decomposer once, then
spec-writer -> developer + tester -> verifier (fresh each round) -> doc-writer +
changelog-writer per item. Request 001 was shipped first (item R1) because U4 depends on
it. Approval recorded in `approvals/approved/build-urs-002.md`.

| Item | Title | URS ids | Result | Fix rounds | Commit |
|------|-------|---------|--------|-----------|--------|
| F0 | Agent entity, Ticket assignee + dueAt, SLA constants, migration, ApiTestCase | F-01.1, F-02.1, F-03.1..4, N-03 | PASS | 1 | c63c5ff |
| R1 | Filter GET /api/tickets by status (request 001) | request 001, N-02, N-03 | PASS | 0 | 5441fa6 |
| U1 | POST/GET /api/agents | F-01.2, F-01.3, N-02 | PASS | 0 | b78d25e |
| U3 | PATCH /api/tickets/{id}/assignee | F-02.2..4, N-01..03 | PASS | 0 | a44175d |
| U2 | PATCH /api/agents/{id} active flag | F-01.4, N-01, N-02 | PASS | 0 | 87b6736 |
| U4 | GET /api/tickets?breached= | F-04.1..3, N-02, N-03 | PASS | 0 | 8fb7f22 |
| U5 | GET /api/reports/sla | F-05.1..3, N-02 | PASS | 0 | 625b2ed |
| FX | URS-N acceptance sweep | N-01..03, section 6 | PASS | 0 | 51f343d |

Suite: 3 tests at the start of the URS, 117 tests (1128 assertions) at the end. Standard
traffic replay after FX: HTTP 200: 6, 201: 2, 404: 1, 422: 3, no 5xx, zero CRITICAL.

Approximate cost: about 95k output tokens per agent run, 7 to 8 runs per item, so
roughly 650k to 750k tokens per item and 5.5M for the eight items including the three
release-level agents.

## Traceability

Section 7 of `docs/urs/URS-002-sla-module.md`: every URS-F and URS-N row is PASS with
spec and test paths.

## Decisions taken by the orchestrator

- U1: accepted the addition of `symfony/property-access` and `symfony/property-info`
  (required by `#[MapRequestPayload]`), flagged by the verifier as touching files the
  spec listed out of scope. The spec-writer role now checks for them.
- FX: `urgent` is not added as a priority. Request 002 names no SLA target, and under
  URS-F-03.2 it would default to 24 h, looser than `high`. POST with `urgent` is a 422.
  Request 002 stays in the inbox for its own item.

## Open points for the human

- `requests/002-urgent-priority.md`: decide the SLA target for `urgent`, then run
  `/improve`.
- Tag and merge of `build/urs-002` after reading the release dossiers (0.2.0 and 0.3.0).

## Lessons encoded during this build

- Shared SQLite test database made concurrent suite runs flaky: each PHPUnit process now
  gets its own file (`tests/bootstrap.php`, `.env.test`).
- The orchestrator must not switch branches while agents run; one brief checkout
  mid-run made a doc-writer re-read its inputs. Rule added to `/improve`.
- Ownership of `tests/` must be stated in both build prompts (from the F0 round).
