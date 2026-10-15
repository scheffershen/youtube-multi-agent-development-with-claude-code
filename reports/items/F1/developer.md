SPEC: D:\Sandbox\yt-30-10-2026\ticketflow\specs\F1-urgent-priority.md
FILES CHANGED:
- D:\Sandbox\yt-30-10-2026\ticketflow\src\Entity\Ticket.php: `PRIORITIES` is now `['low', 'normal', 'high', 'urgent']`.
- D:\Sandbox\yt-30-10-2026\ticketflow\src\Dto\CreateTicketRequest.php: added `#[Assert\Choice(choices: Ticket::PRIORITIES)]` next to `NotBlank` on `$priority`.
- D:\Sandbox\yt-30-10-2026\ticketflow\src\Repository\TicketRepository.php: new `countGroupedByPriority()`, which counts tickets per priority in one GROUP BY query.
- D:\Sandbox\yt-30-10-2026\ticketflow\src\Service\TicketStats.php: `summary()` now adds `by_priority`, with the keys low, normal, high and urgent (0 when there are none) plus `other` = total minus the four known counts, so the five values always add up to `total`. The `@return` docblock is updated and the existing keys are unchanged.
SCHEMA CHANGED: no (D1=A; no migration was added and no rows are modified)
TESTS: OK (33 tests, 174 assertions). This includes the tester's tests\Api\F1UrgentPriorityTest.php; I did not create or edit any test class.
NOTES: The 422 `{"errors":{"priority":...}}` shape still comes from the existing TicketValidationExceptionListener. That listener keeps only the first message for each field, so a blank `priority` (which fails both NotBlank and Choice) returns a single NotBlank message. `priority: 5` is caught as a type mismatch during deserialisation and returns 4xx JSON, never 500 (covered by the tester's test). Choice skips null values, so a missing priority still produces only the NotBlank message from B2. Decisions applied: D1=A, D2=B, D3=B.
