SPEC: specs/B3-show-ticket-not-found.md
FILES CHANGED: D:\Sandbox\yt-30-10-2026\ticketflow\src\Controller\TicketController.php (added a null check in `show`. When the ticket is missing it returns `$this->json(['error' => 'Ticket not found'], Response::HTTP_NOT_FOUND)`, the same response `changeStatus` already gives.)
SCHEMA CHANGED: no
TESTS: OK (16 tests, 105 assertions)
NOTES: none. Per my instructions, I did not create or edit any test class. Those 16 tests are the suite as it stood when I ran it, so the tester's B3 tests may not have been in it yet.
