# D1: When POST /api/tickets has no `priority` key, should the API return a validation error (and which status code), or create the ticket with a default priority?

**Item:** B2   **Role:** spec-writer   **Date:** 2026-10-15
**Why it is a decision:** It changes the API contract (status code, JSON keys, defaults), and the backlog item states no expectation, so the choice is open.

| Option | Consequence |
|--------|-------------|
| A. Return 422 with `{"errors": {"priority": "..."}}` and store nothing | Matches the existing 422 convention in `changeStatus` (TicketController.php:68). A client that omits `priority` gets a clear error. It stays compatible with F1, which can later add the "not in allowed values" check to the same error shape. No working client is affected, because omitting priority currently returns 500. |
| B. Return 400 with `{"errors": {"priority": "..."}}` | The same behaviour as A with a different status code. It differs from the 422 already used for an invalid status, so the API becomes inconsistent. |
| C. Default a missing priority to `normal` and return 201 | Omitting `priority` becomes legal and the ticket is stored as `normal`. This is friendlier for clients but permanently widens the contract. F1 would then have to say that an explicit null or empty string is rejected while an absent key is defaulted. It also hides client mistakes. |

**Recommendation:** A - it reuses the 422 already in the codebase and keeps F1 simple. In F1, "missing" and "unknown" can both be 422 on the same `errors.priority` key.
**Work so far:** I read `TicketController.php`, `Ticket.php` (`PRIORITIES` = low, normal, high), `TicketApiTest.php` and `requests/002-urgent-priority.md`. The crash is at TicketController.php:40, where `$data['priority']` is read without a check. `title` and `description` have the same unchecked access on lines 38 to 40. Whether B2 should also cover them is a second scope question. The spec skeleton is ready. It needs the status code and shape from D1, an acceptance criterion that no ticket is persisted on error, and an out-of-scope note that no new priority values are added (F1). I have not written the spec file.
**Decision:**
