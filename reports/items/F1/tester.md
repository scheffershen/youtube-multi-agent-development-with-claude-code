SPEC: D:\Sandbox\yt-30-10-2026\ticketflow\specs\F1-urgent-priority.md
TEST FILE: D:\Sandbox\yt-30-10-2026\ticketflow\tests\Api\F1UrgentPriorityTest.php
CRITERIA COVERED: AC1, AC2, AC3, AC4, AC5, AC6, AC7, AC8, AC9, AC10, AC11, AC12. All are HTTP-level, except AC1, which is a plain constant assertion in the same file. No tests were put under tests/Unit/.
CRITERIA NOT COVERED: none. AC12 is covered by new assertions on the existing stats keys. I did not re-run `testStatsWithTickets`, and I did not modify any existing test.
NOTES:
- Fixture for AC10 and AC11: a `Ticket` with priority `critical`, inserted through the EntityManager.
- In AC12, `closed_ratio` is a percentage (50 for 1 closed out of 2), not a 0-1 fraction. I read this from the actual output of the current code, and the assertion checks only that value.
RUN RESULT: Tests: 12, Assertions: 40, Failures: 6. The 6 failures are AC1, AC3, AC4, AC8, AC9 and AC10. They fail because the feature is not implemented yet: the constant lacks `urgent`, there is no priority validation, and `by_priority` is missing from the stats. The other 6 tests pass against the current code: AC2, AC5, AC6, AC7, AC11 and AC12.
