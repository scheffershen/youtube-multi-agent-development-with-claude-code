# D1: What happens to existing ticket rows whose stored priority is outside low, normal, high, urgent?

**Item:** F1   **Role:** spec-writer   **Date:** 2026-10-15
**Why it is a decision:** It concerns data: migration strategy, defaults for existing rows, deleting or rewriting data.

| Option | Consequence |
|--------|-------------|
| A. Leave existing rows untouched, no migration | Rows with 'urgent' become valid as soon as urgent is allowed. Any other out-of-list value stays stored and is returned as-is by GET /api/tickets and GET /api/tickets/{id}. Only new writes are validated. Smallest diff. |
| B. Doctrine migration rewriting out-of-list priorities to 'normal' ('urgent' kept) | Every stored row ends up valid. The original junk values are lost; a migration file is added. |
| C. Doctrine migration deleting rows with an out-of-list priority | Data is destroyed. Not recommended. |
| D. Leave the data alone, but reads and stats report out-of-list priorities under a fixed bucket (e.g. 'unknown') | No data loss, no migration. Adds a new visible value to the read contract. |

**Recommendation:** A - 'urgent' is the only known out-of-list value, and the new list makes those rows valid with no change. Rewriting or deleting data would need a stronger reason.
**Work so far:** Read Ticket.php, CreateTicketRequest.php, TicketController.php, TicketStats.php, TicketApiTest.php, requests/002-urgent-priority.md, decision-B2-D1.md. Plan: add `#[Assert\Choice(choices: Ticket::PRIORITIES)]` to CreateTicketRequest::priority, 422 `{"errors":{"priority":"..."}}` per B2-D1=A, nothing stored on error. A second question (D2) on ranking, stats breakdown and filters follows once D1 is answered.
**Decision:**
