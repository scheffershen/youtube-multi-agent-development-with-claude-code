SPEC: specs/U5-sla-report.md
FILES CHANGED: src/Repository/TicketRepository.php (countByPriority(?bool $breached, ?DateTimeImmutable $now), stacks withBreached()), src/Service/SlaReport.php (new), src/Controller/SlaReportController.php (new, GET /api/reports/sla, route report_sla, JSON_PRESERVE_ZERO_FRACTION)
SCHEMA CHANGED: no
TESTS: OK (104 tests, 921 assertions) solo, three consecutive green runs
NOTES: intermediate full-suite flakiness while the tester's phpunit overlapped on the shared var/data_test.db; not spec-related.
