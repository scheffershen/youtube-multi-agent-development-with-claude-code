SPEC: specs/F0-sla-foundation.md
TEST FILE: tests/Api/F0SlaFoundationTest.php (extends ApiTestCase; AC2, AC4-AC15) and tests/Unit/F0SlaFoundationMigrationTest.php (AC1, subprocess migrations on temp SQLite files inspected with PDO)
Fix round 1: TicketApiTest moved onto ApiTestCase (AC5); migration test builds its own fixture instead of copying var/data_dev.db (AC1).
CRITERIA COVERED: AC1, AC2, AC4, AC5, AC6, AC7, AC8, AC9, AC10, AC11, AC12, AC13, AC14, AC15
CRITERIA NOT COVERED: AC3 (it is the existing suite itself; its three methods keep their assertions and pass). AC5's "without changing its assertions" clause is not testable by reflection.
RUN RESULT: tools/php vendor/bin/phpunit: OK (18 tests, 160 assertions)
