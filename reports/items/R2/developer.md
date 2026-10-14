SPEC: specs/R2-urgent-priority.md
FILES CHANGED: src/Entity/Ticket.php (PRIORITIES gains 'urgent' after high; SLA_TARGET_HOURS gains 'urgent' => 2)
SCHEMA CHANGED: no
TESTS: OK (126 tests, 1302 assertions) after the tester's amendments
DECISIONS: D1=B, D2=A, D3=A
NOTES: Raised D3 (spec under-counted U5 three-row assertions); resolved by a person; implementation unchanged by it. Simulator: 200:6 201:3 404:1 422:2, no 5xx.
