# Dashboard is blank every Monday morning

**From:** Mara (support lead)
**Observed:** After the weekly cleanup job empties the ticket table, the dashboard shows a
red "An error occurred" box until the first new ticket arrives. I think the stats call
is failing.
**Expected:** With zero tickets the stats should just show zeros.

---
Resolved in build/urs-002 by the FX acceptance sweep (specs/FX-urs-n-acceptance-sweep.md): GET /api/stats returns zeros on an empty table.
