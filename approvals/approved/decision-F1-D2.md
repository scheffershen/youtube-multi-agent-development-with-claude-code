# D2: What must F1 deliver for "ranked above high" and "stats and filters include urgent", given that no ranking, priority filter or priority breakdown exists today?

**Item:** F1   **Role:** spec-writer   **Date:** 2026-10-15
**Why it is a decision:** It changes the API contract and the scope (new JSON keys, query parameters or sort behaviour), and the requirement is ambiguous: request 002 mentions a dashboard, stats and filters that this repo does not have.

Current state: Ticket::PRIORITIES is ['low','normal','high'] and nothing else uses it. GET /api/tickets takes no query parameters (newest first). GET /api/stats has no priority data. There is no dashboard.

| Option | Consequence |
|--------|-------------|
| A. Validation only: PRIORITIES = ['low','normal','high','urgent']; POST rejects other values with 422 errors.priority; urgent accepted with 201 | Smallest diff, no new contract. "Ranked above" is only the constant's order; "stats and filters include urgent" is satisfied vacuously. |
| B. A + `by_priority` object in GET /api/stats (all four keys always present, 0 when empty) | One new additive key in stats; existing keys unchanged. Ranking is still only the constant's order. |
| C. B + `?priority=<value>` filter on GET /api/tickets (unknown value -> 422 errors.priority, count reflects the filter) | New query parameter and a new error case. Default behaviour unchanged. |
| D. C + `?sort=priority` on GET /api/tickets (urgent, high, normal, low; newest first as tiebreak) | The only option where the ranking is testable over HTTP. Most contract and scope; needs a rank map or CASE in the repository. |

**Recommendation:** B - covers "stats include urgent" with one additive key without inventing filter and sort contracts the request never specified. Choose D if the ranking must be visible through the API.
**Work so far:** D1=A applied. Spec outline valid for every option: PRIORITIES order, `#[Assert\Choice]` + NotBlank on CreateTicketRequest::priority, urgent -> 201, 'critical' / 'HIGH' / 5 -> 422 with nothing stored, missing/blank as in B2. Out of scope: changeStatus, existing rows, SimulateTrafficCommand, the entity column.
**Orchestrator note:** F2 (status filter on GET /api/tickets, request 001) is still open for a later round. Options C and D add query parameters to the same endpoint, so they would set the convention F2 follows.
**Decision:** B by Shen on 2026-10-15 (chat) - validation plus by_priority in /api/stats; no filter, no sort
