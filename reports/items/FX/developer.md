SPEC: specs/FX-urs-n-acceptance-sweep.md
FILES CHANGED: src/Dto/CreateTicketRequest.php (new), src/Controller/TicketController.php (create() via MapRequestPayload, show() 404 JSON), src/Service/TicketStats.php (zero guard)
SCHEMA CHANGED: no
TESTS: Tests: 104, Failures: 1 at hand-back (F0 AC11 urgent->201, the amendment assigned to the tester)
NOTES: app:simulate-traffic --reset: HTTP 200:6, 201:2, 404:1, 422:3, no 5xx, zero CRITICAL and zero ERROR appended. Decision: 'urgent' NOT added to PRIORITIES (request 002 names no SLA target; under URS-F-03.2 it would get 24 h, looser than high); POST 'urgent' -> 422; request 002 stays open. Request 003 resolved by the zero guard.
