# D1: Reject "urgent" as a ticket priority (422) rather than add it, in item FX?

**Item:** FX   **Role:** developer, confirmed by orchestrator (decision taken without a person on 2026-10-14)
**Why it is a decision:** API contract - a value previously stored (201) is now rejected (422); conflicts with open request 002

| Option | Consequence |
|--------|-------------|
| A. Ratify | `urgent` stays rejected. Request 002 remains open and gets its own item once a person states the SLA target for `urgent` (URS-F-03.2 would otherwise give it 24 h, looser than `high`). Rows already stored with `urgent` are served unchanged. |
| B. Reverse: add `urgent` now with an SLA target you state here | New item: add to `Ticket::PRIORITIES` and `SLA_TARGET_HOURS`, update the SLA report rows, amend the FX test for `urgent`, update docs and flash info 0.3.0. Closes request 002. |
| C. Reverse: add `urgent` with the normal 24 h target | Same as B with the fallback target; contradicts the request's intent ("ranked above high"). |

**Recommendation:** A - the URS does not define `urgent`; adding it with a guessed target would be exactly the kind of decision this protocol forbids.
**Work so far:** FX committed in 51f343d; documented in docs/user/tickets.md; mentioned in flash info 0.3.0 as a change for API users.
**Decision:**
