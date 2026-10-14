SPEC: specs/R1-filter-tickets-by-status.md
FILES CHANGED: src/Repository/TicketRepository.php, src/Controller/TicketController.php
SCHEMA CHANGED: no
TESTS: OK (28 tests, 226 assertions)
NOTES: Repository exposes createNewestFirstQueryBuilder(), withStatus(QueryBuilder, string), findNewestFirst(?string). Controller validates status with in_array strict and returns 400 JSON {"error":"Invalid status","allowed":[...]} for any non-member value including arrays; otherwise delegates.
