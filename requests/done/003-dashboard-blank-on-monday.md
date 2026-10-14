# Dashboard is blank every Monday morning

**From:** Mara (support lead)
**Observed:** After the weekly cleanup job empties the ticket table, the dashboard shows a
red "An error occurred" box until the first new ticket arrives. I think the stats call
is failing.
**Expected:** With zero tickets the stats should just show zeros.

---
Resolved in round 1 (spec specs/B1-stats-zero-tickets.md).
