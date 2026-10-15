SPEC: specs/F2-filter-tickets-by-status.md
FILES CHANGED:
- D:\Sandbox\yt-30-10-2026\ticketflow\src\Dto\ListTicketsQuery.php (new): a `readonly` DTO with a nullable `status`. Its `#[Assert\Choice(choices: Ticket::STATUSES, message: 'Unknown status {{ value }}. Allowed values: {{ choices }}.')]` takes the allowed values from `Ticket::STATUSES`, so there is no second list.
- D:\Sandbox\yt-30-10-2026\ticketflow\src\Controller\TicketController.php: `list()` now takes `#[MapQueryString(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)] ListTicketsQuery $query = new ListTicketsQuery()` and passes `$query->status` to the repository.
- D:\Sandbox\yt-30-10-2026\ticketflow\src\Repository\TicketRepository.php: `findAllNewestFirst(?string $status = null)` filters in the query (`findBy`), and the newest-first ordering is unchanged.
- D:\Sandbox\yt-30-10-2026\ticketflow\src\EventListener\TicketValidationExceptionListener.php: the route list is now `['ticket_create', 'ticket_list']`. The response uses `$exception->getStatusCode()` instead of a hard-coded 422, so it is 422 for create (the MapRequestPayload default) and 400 for list.
SCHEMA CHANGED: no (no migration)
TESTS: OK (44 tests, 238 assertions). The full suite includes the tester's tests/Api/F2FilterTicketsByStatusTest.php, and that file on its own gives OK (11 tests, 64 assertions).
NOTES: No new decisions; D1=C and D2=A are applied as recorded. I ran a scratch kernel check on a separate SQLite file, which has been deleted:
- `?status=open` and `?status=closed` return 200 with only matching tickets; `?status=in_progress` with no matching tickets returns 200 `{"items":[],"count":0}`.
- No parameter returns all tickets.
- `bogus`, `OPEN` and an empty value return 400 `{"errors":{"status":"Unknown status \"bogus\". Allowed values: \"open\", \"in_progress\", \"closed\"."}}`. An empty value stays `''` after the Serializer and is rejected by Choice.
- `?status[]=open` returns 400 `{"errors":{"status":"This value should be of type null|string."}}`, not 500. The spec leaves this case unspecified.
- `POST {}` still returns 422 with the B2 `errors` map. PATCH was not touched.
I did not change any test class.
