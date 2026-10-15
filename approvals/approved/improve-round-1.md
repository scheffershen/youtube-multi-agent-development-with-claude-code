# Build these 4 items in round 1?

**Context:** round 1, 2026-10-15, branch demo-start, commit cd55fb3. Backlog from the
log-analyst triage of this session (logs and requests unchanged since), `--max-items 4`.
**Options:** approve | reject | edit (edit the table below, then move to approved/)

| Item | Title | Evidence | Depends on |
|------|-------|----------|-----------|
| B1 | Return zeros from /api/stats when there are no tickets | var/log/dev.log DivisionByZeroError TicketStats.php:29; requests/003-dashboard-blank-on-monday.md | - |
| B2 | Handle a missing priority on POST /api/tickets instead of crashing | var/log/dev.log "Undefined array key priority" TicketController.php:40 | overlaps F1 (priority validation) |
| B3 | Return 404 for GET /api/tickets/{id} when the ticket does not exist | var/log/dev.log "toArray() on null" TicketController.php:54 | - |
| F1 | Add 'urgent' priority ranked above high and reject unknown priorities | requests/002-urgent-priority.md; var/log/dev.log | overlaps B2 |

Not selected: F2 (honour the status query filter, requests/001-filter-tickets-by-status.md).

**Decision:** approve

Approved by Shen on 2026-10-15 (chat)
