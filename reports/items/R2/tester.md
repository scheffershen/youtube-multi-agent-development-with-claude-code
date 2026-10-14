SPEC: specs/R2-urgent-priority.md
TEST FILE: tests/Api/R2UrgentPriorityTest.php (9 methods)
CRITERIA COVERED: AC1-AC10 (AC10 = the amendments plus the suite run; TicketApiTest untouched)
CRITERIA NOT COVERED: none
AMENDMENTS: per RB8/AC10: FX testAc2 (renamed, urgent no longer rejected), FX EXPECTED_STATUSES index 4 422->201, FX testAc11 counts, F0 testAc11 (urgent 201, 2 h), U5 KNOWN_PRIORITIES + testAc6 (unknown value now 'escalated'). Per D3=A: U5 testAc1 (renamed, 4 rows), testAc2 (trailing urgent zero row), testAc4, testAc10 (assertCount 4). Each carries a docblock line citing R2 and the decision.
RUN RESULT: OK (126 tests, 1302 assertions); new file alone OK (9 tests, 170 assertions)
DECISIONS: D1=B, D2=A, D3=A
