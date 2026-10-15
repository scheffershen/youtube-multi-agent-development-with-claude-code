SPEC: specs/F2-filter-tickets-by-status.md
TEST FILE: D:\Sandbox\yt-30-10-2026\ticketflow\tests\Api\F2FilterTicketsByStatusTest.php
CRITERIA COVERED: AC1, AC2, AC3, AC4, AC5, AC6, AC7, AC8, AC9, AC10, AC11. AC12 is covered by the existing TicketApiTest methods, which I left unmodified.
CRITERIA NOT COVERED: none. AC12 needs no new test.
RUN RESULT: Tests: 11, Assertions: 45, Failures: 7. These are the expected failures, because the filter is not implemented yet. The failing tests are the filter ones (AC1, AC2, AC6, AC7, AC8, AC9 and one more). They fail because `?status=` is ignored and the endpoint returns 200 with the full list. AC3, AC4, AC5, AC10 and AC11 pass. There were no errors from typos or setup in the test file.
