# D1: Which 400 error body shape must GET /api/tickets?status=<unknown> return?

**Item:** F2   **Role:** spec-writer   **Date:** 2026-10-15
**Why it is a decision:** It changes the API contract (JSON keys and status code for an error); the backlog item marks it as a contract decision for a person.

| Option | Consequence |
|--------|-------------|
| A. B2 shape `{"errors": {"status": "<message>"}}` via #[MapQueryString] + Choice, listener extended to this route returning 400 | Consistent with POST validation errors and AGENTS.md; one `errors` map for clients. Allowed values not listed unless the message includes them. Listener covers a second route and returns 400 here vs 422 for B2. |
| B. PATCH shape `{"error": "Invalid status", "allowed": [...]}` | Lists allowed values, matches PATCH status. Needs manual handling or a separate listener; differs from B2's shape and sits oddly with #[MapQueryString]. |
| C. B2 `errors` map with the allowed values in the message text | One shape for clients, allowed values still discoverable. Tests assert only the `errors.status` key, not the exact text. |

**Recommendation:** C - keeps the `errors` shape of the newest endpoint, fits #[MapQueryString] and the Validator, and keeps the allowed values visible.
**Work so far:** Read TicketController.php (PATCH status validation at lines 60-77: 422, `error` + `allowed`). property-access/info already present, so no new dependency. Valid whatever the answer: known status filters (200, existing shape); absent status unchanged; unknown status 400; case-sensitive match on Ticket::STATUSES; out of scope: PATCH, POST (B2 stays 422), priority filter, pagination; existing tests unchanged.
**Decision:** C by Shen on 2026-10-15 (chat) - 400 with errors.status, message names the allowed values
