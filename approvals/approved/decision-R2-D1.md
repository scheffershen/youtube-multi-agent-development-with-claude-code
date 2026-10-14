# D1: Add "urgent" as a ticket priority, and with which SLA target?

**Item:** R2   **Role:** orchestrator (question raised by request 002; earlier ratify-FX-D1 kept urgent rejected pending this target)   **Date:** 2026-10-14
**Why it is a decision:** API contract (accepted values) and data (SLA target for new rows); conflicts with the FX decision that rejected the value

| Option | Consequence |
|--------|-------------|
| A. Keep rejecting urgent | Request 002 stays open. |
| B. Add urgent with a target stated by a person | New priority accepted on creation, ranked above high, own SLA target; SLA report gains a row; FX test and docs amended. Closes request 002. |

**Recommendation:** none; the target is a business value only a person can give.
**Work so far:** none.
**Decision:** B with target 2 hours, by Yi Shen on 2026-10-14 (chat, Claude Code Desktop) - "urgent SLA target is 2 hours, close request 002". Supersedes ratify-FX-D1.
