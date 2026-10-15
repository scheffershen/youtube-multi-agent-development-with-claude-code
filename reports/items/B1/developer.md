SPEC: specs/B1-stats-zero-tickets.md
FILES CHANGED: D:\Sandbox\yt-30-10-2026\ticketflow\src\Service\TicketStats.php
SCHEMA CHANGED: no
TESTS: OK (6 tests, 26 assertions)
NOTES: In `TicketStats::summary()`, `closed_ratio` is now guarded: `0 === $total ? 0.0 : round($closed / $total * 100, 1)`. On an empty table it returns the float 0.0. JsonResponse encodes that as the number `0`, never null or a string. With one or more tickets the calculation is the same as before. The controller and the JSON keys are unchanged, and no migration was needed. I did not touch any test class. The suite has 6 tests. Only 3 are in `TicketApiTest`, so the tester has probably added the other 3 already, and all 6 pass.
