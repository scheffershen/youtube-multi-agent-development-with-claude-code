# D2: Where does `urgent` sit in `Ticket::PRIORITIES`, and therefore in the row order of `GET /api/reports/sla`?

**Item:** R2   **Role:** spec-writer   **Date:** 2026-10-14
**Why it is a decision:** API contract: the report's row order is documented (docs/user/statistiques.md) and asserted by existing tests (U5, FX); request 002 says "ranked above high" but not which end of the list.

| Option | Consequence |
|--------|-------------|
| A. Append after high: low, normal, high, urgent | Ascending severity as today; report rows low, normal, high, urgent, (other); existing rows keep their index, positional readers unaffected (URS-N-03). Docs add one line. |
| B. Place first: urgent, low, normal, high | Most severe first; every existing row shifts by one; positional readers break; docs rewrite the order sentence. |

**Recommendation:** A - the only reading under which "ranked above high" and URS-N-03 hold at once.
**Work so far:** specs/R2-urgent-priority.md written in full with every order-dependent spot marked [D2]; D1=B cited; four shipped tests to amend listed with line numbers.
**Decision:**
A by Yi Shen on 2026-10-14 (chat, Claude Code Desktop) - append after high
