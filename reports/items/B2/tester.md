SPEC: specs/B2-create-ticket-missing-fields.md
TEST FILE: D:\Sandbox\yt-30-10-2026\ticketflow\tests\Api\B2CreateTicketMissingFieldsTest.php
CRITERIA COVERED: AC1, AC2, AC3, AC4, AC5, AC6, AC7, AC8, AC9, AC11. The AC1 to AC7 tests also assert that nothing is persisted, and AC8 runs all the failing payloads in one test.
CRITERIA NOT COVERED: AC10. It refers to existing tests in TicketApiTest.php, which I did not modify, so there was nothing new to write. I did not test the "no 'Ticket created' log line" part of requirement 4, because it is not an acceptance criterion.
RUN RESULT: Tests: 10, Assertions: 19, Failures: 8, Warnings: 3. The failures are expected, since the developer's work is not in yet. The 8 failing tests are AC1 to AC8, which hit the unguarded array access. AC9 and AC11 pass. I made no changes to `src/` or to existing tests.
