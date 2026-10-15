# D2: What does GET /api/tickets?status= (empty value) return?

**Item:** F2   **Role:** orchestrator (raised from the spec-writer's D1 report, which asked a person to confirm it)   **Date:** 2026-10-15
**Why it is a decision:** It fixes the API contract for an edge value (status code and result for an empty parameter).

| Option | Consequence |
|--------|-------------|
| A. Treat it as unknown: 400 with the D1 error shape | Strict; a client bug that sends an empty value is surfaced. A UI that clears a filter by sending `status=` gets an error. |
| B. Treat it as absent: 200 with all tickets | Lenient; a cleared filter input just works. An empty value is silently ignored. |

**Recommendation:** A - the spec-writer's proposal; it matches "unknown status returns 400, not a list".
**Work so far:** Same as D1.
**Decision:**
