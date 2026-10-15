# Round 2 - 2026-10-15

| Item | Title | Result | Fix rounds | Decisions | Commit |
|------|-------|--------|-----------|-----------|--------|
| F2 | Filter GET /api/tickets by status and return 400 on an unknown status | PASS | 0 | D1=C, D2=A | 4238f25 |

Backlog approved in chat (approvals/approved/improve-round-2.md). D2 (empty `status=`)
was raised by the orchestrator from a point the spec-writer asked a person to confirm.

## Remaining CRITICALs (from verifier)

None. The traffic replay appended no CRITICAL, ERROR or WARNING lines. Note: the traffic
simulator never sends `?status`, so the filter path is covered by tests only.

## Requests still open

None. 001 resolved by F2 and moved to `requests/done/`.

## Suggestions for the human

- **Validation listener is now shared.** `TicketValidationExceptionListener` serves
  `ticket_create` (422) and `ticket_list` (400) and takes the status from the exception.
  A third route will need adding to its route list.
- **Traffic simulator gap.** Add `?status=` requests to `app:simulate-traffic` so the
  verifier's replay exercises the filter.
- **Unspecified input.** `?status[]=open` returns 400 with a type message (not 500);
  no spec fixes this, so it is not a guaranteed contract.
- **Error shapes.** POST and GET list use the `errors` map; PATCH status and 404 still use
  `error` (+ `allowed`). Aligning them would be an API change.
- `release/unreleased/` now holds B1, B2, B3, F1 and F2: ready for `/release`.
