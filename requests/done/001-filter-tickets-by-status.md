# Filter the ticket list by status

**From:** Mara (support lead)
**Observed:** `GET /api/tickets?status=open` returns every ticket, including closed ones.
The query parameter is silently ignored.
**Expected:** When `status` is given, only tickets with that status are returned. When it
is given but not one of the known statuses, I would rather get a 400 with a message than
an empty list, so I notice my typo.
**Why it matters:** The support dashboard polls this endpoint every 30 seconds and we are
paging through hundreds of closed tickets to find the open ones.

---
Resolved in build/urs-002 as item R1 (spec specs/R1-filter-tickets-by-status.md).
