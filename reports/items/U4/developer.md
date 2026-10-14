SPEC: specs/U4-breached-filter.md
FILES CHANGED: src/Repository/TicketRepository.php (withBreached(QueryBuilder, bool, DateTimeImmutable); findNewestFirst(?status, ?breached, ?now)), src/Controller/TicketController.php (list() reads breached, strict 'true'|'false', 400 {"error":"Invalid breached","allowed":["true","false"]})
SCHEMA CHANGED: no
TESTS: OK (83 tests, 686 assertions) at hand-back (tester file not yet present)
NOTES: withBreached() is the reusable predicate for U5.
