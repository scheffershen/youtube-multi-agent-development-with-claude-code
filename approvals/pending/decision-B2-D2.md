# D2: Should B2 also validate missing `title` and `description` on POST /api/tickets, or only `priority`?

**Item:** B2   **Role:** spec-writer   **Date:** 2026-10-15
**Why it is a decision:** It changes the scope (adding or dropping acceptance criteria) and the API contract (status codes and error keys for two more fields).

| Option | Consequence |
|--------|-------------|
| A. Cover `priority` only | The diff stays minimal and matches the backlog item. A POST without `title` or `description` still returns 500 (TicketController.php:40, same undefined-key warning). The spec would list this as out of scope, and it would need a new backlog item. |
| B. Cover `title`, `description` and `priority`; return 422 with `{"errors": {"<field>": "..."}}` for each missing or empty field, all failing fields in one response | No more 500s on missing fields from this endpoint. The error shape is consistent across fields and compatible with F1. The scope grows beyond the logged evidence, with two or three more acceptance criteria and tests. |
| C. Cover `priority` only, but make the error shape generic (`errors` keyed by field) | Same behaviour as A today. A later item adds the other fields without a breaking change. Missing title or description still returns 500 until then. |

**Recommendation:** B - it is the same bug (an unchecked array key at line 40), the fix is the same mechanism, and a half-fixed endpoint would leave two known 500s.
**Work so far:** D1=A is recorded. A missing `priority` returns 422 with `{"errors":{"priority":"..."}}` and nothing is persisted. I have read all the relevant code (TicketController.php, Ticket.php, TicketApiTest.php, requests/002-urgent-priority.md). The spec file has not been written; it will be written as soon as D2 is answered.
**Decision:**
