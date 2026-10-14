# Build URS-002: ship request 001 first, then items U1 to FX in order?

**Context:** URS-002 SLA module, 2026-10-14, branch `build/urs-002`, after F0 (commit c63c5ff) and release 0.2.0.
**Options:** approve | reject | edit

| Item | Title | Evidence | Depends on |
|------|-------|----------|-----------|
| R1 | Filter GET /api/tickets by status (request 001) | requests/001-filter-tickets-by-status.md | F0 |
| U1 | POST /api/agents, GET /api/agents | URS-F-01.2, URS-F-01.3 | F0 |
| U2 | PATCH /api/agents/{id} active flag | URS-F-01.4 | F0, U1 |
| U3 | PATCH /api/tickets/{id}/assignee | URS-F-02.2..4 | F0, U1 |
| U4 | GET /api/tickets?breached= combinable with status | URS-F-04.1..3 | F0, R1 |
| U5 | GET /api/reports/sla | URS-F-05.1..3 | F0, U4 |
| FX | URS-N acceptance sweep | URS-N-01..03 | all |

**Decision:** Approved by Yi Shen on 2026-10-14 (chat: "ship request 001 first, then continue the build").
