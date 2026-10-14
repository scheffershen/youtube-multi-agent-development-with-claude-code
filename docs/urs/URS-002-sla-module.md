# URS-002: TicketFlow SLA and Assignment Module

| Field | Value |
|-------|-------|
| Document | URS-002 |
| Version | 1.0 |
| Status | Approved for build |
| System | TicketFlow API |
| Author | Mara Lindqvist, Support Operations |
| Reviewer | Dev Okafor, Engineering |
| Date | 2026-10-14 |

## 1. Purpose

Support tickets are currently created and closed with no record of who is working on
them and no measure of how long they took. This document states the user requirements
for an SLA (service-level agreement) and assignment module so that support operations
can see which tickets are at risk and report breach rates to management.

## 2. Scope

In scope: a registry of support agents, assignment of a ticket to one agent, an SLA due
time derived from ticket priority, a way to list tickets that have breached their SLA,
and a summary report. All functions are exposed through the existing JSON API.

Out of scope: authentication and authorisation, email or chat notifications, a web user
interface, SLA pause or clock-stop rules, historical reassignment audit beyond the log.

## 3. Definitions

- **Agent**: a member of the support team who can be assigned tickets. Not to be
  confused with software agents.
- **Due time**: the instant by which a ticket should be closed, computed from its
  creation time and priority.
- **Breached**: a ticket that is not closed and whose due time has passed.

## 4. Functional requirements

Each requirement is numbered for traceability. "Shall" denotes a mandatory requirement.

### URS-F-01 Agent registry

1. The system shall store support agents with a display name, an email address and an
   active flag.
2. Email addresses shall be unique across agents. Creating an agent with an email that
   already exists shall be rejected with a validation error.
3. The system shall expose `POST /api/agents` to create an agent (name, email; active
   defaults to true) and `GET /api/agents` to list agents, active ones first.
4. The system shall expose `PATCH /api/agents/{id}` to change the active flag.

### URS-F-02 Ticket assignment

1. A ticket shall have at most one assignee, which is an agent.
2. The system shall expose `PATCH /api/tickets/{id}/assignee` with the agent id in the
   body. Assigning to an unknown agent shall return 404. Assigning to an inactive agent
   shall be rejected with a validation error and the ticket shall remain unchanged.
3. Sending `null` as the agent id shall unassign the ticket.
4. The ticket JSON returned by every ticket endpoint shall include the assignee as an
   object with id and name, or `null`.

### URS-F-03 SLA due time

1. At creation, every ticket shall receive a due time equal to its creation time plus
   the target for its priority: low 72 hours, normal 24 hours, high 8 hours.
2. Any priority added to the system after this document shall default to the normal
   target unless its own requirement states otherwise.
3. The due time shall be stored, not recomputed, so that a later change to the targets
   does not alter existing tickets.
4. The ticket JSON shall include the due time in ISO 8601 format.

### URS-F-04 Breach listing

1. The system shall expose `GET /api/tickets?breached=true` returning only tickets that
   are breached as defined in section 3.
2. `breached=false` shall return only tickets that are not breached. Any other value
   shall be rejected with a 400.
3. The breach filter shall be combinable with any status filter that exists on the same
   endpoint.

### URS-F-05 SLA report

1. The system shall expose `GET /api/reports/sla` returning, for each priority, the
   total number of tickets, the number breached, and the breach rate as a percentage
   rounded to one decimal.
2. The report shall include every priority known to the system even when its total is
   zero, in which case the breach rate shall be 0.
3. The report shall never fail because the ticket table is empty.

## 5. Non-functional requirements

### URS-N-01 Audit logging

Every assignment, unassignment and agent activation change shall be written to the
application log at level `info` with the ticket or agent id and the new value.

### URS-N-02 Error behaviour

All error responses shall be JSON. Client faults (bad input, unknown id) shall never
produce an HTTP 500.

### URS-N-03 Backward compatibility

Existing endpoints and their JSON shapes shall keep working; new fields may be added to
ticket JSON but none removed.

## 6. Acceptance

The module is accepted when every requirement in sections 4 and 5 has at least one
automated test that exercises it through the HTTP API, all tests pass, and a traffic
replay of the standard scenario produces no HTTP 500.

## 7. Traceability

| Requirement | Specification | Test | Status |
|-------------|---------------|------|--------|
| URS-F-01 | F-01.1: specs/F0-sla-foundation.md ; F-01.2, F-01.3: specs/U1-agents-create-list.md | tests/Api/F0SlaFoundationTest.php ; tests/Api/U1AgentsCreateListTest.php | F-01.1 PASS ; F-01.2 PASS ; F-01.3 PASS |
| URS-F-02 | F-02.1: specs/F0-sla-foundation.md | tests/Api/F0SlaFoundationTest.php | F-02.1 PASS |
| URS-F-03 | specs/F0-sla-foundation.md | tests/Api/F0SlaFoundationTest.php | PASS |
| URS-F-04 | | | |
| URS-F-05 | | | |
| URS-N-01 | | | |
| URS-N-02 | specs/R1-filter-tickets-by-status.md ; specs/U1-agents-create-list.md (partial) | tests/Api/R1FilterTicketsByStatusTest.php ; tests/Api/U1AgentsCreateListTest.php | partial, FX pending |
| URS-N-03 | specs/F0-sla-foundation.md (partial) | tests/Api/F0SlaFoundationTest.php | partial, FX pending |

The build loop fills this table: the spec-writer records the spec path, the tester the
test file, the orchestrator the status after the verifier's verdict.
