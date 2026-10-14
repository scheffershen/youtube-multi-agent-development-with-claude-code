<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Ticket;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Spec: specs/R2-urgent-priority.md (request 002, URS-F-03.1/03.2/03.3, URS-F-05.1/05.2,
 * URS-N-02, URS-N-03). Decisions: D1=B (urgent added, SLA target 2 h), D2=A (urgent
 * appended after high).
 *
 * One test method per acceptance criterion, written from the spec only. AC10 (the four
 * amendments to shipped tests, TicketApiTest.php byte-identical, full suite green) is not
 * a method: it lives in the amended files and in the suite run.
 */
final class R2UrgentPriorityTest extends ApiTestCase
{
    private const array TICKET_KEYS = ['id', 'title', 'description', 'priority', 'status', 'createdAt', 'dueAt', 'assignee'];

    private const array STATS_KEYS = ['total', 'open', 'in_progress', 'closed', 'closed_ratio'];

    private const array ENVELOPE_KEYS = ['generatedAt', 'priorities'];

    private const array ENTRY_KEYS = ['priority', 'total', 'breached', 'breach_rate'];

    private const array URGENT_PAYLOAD = ['title' => 'Payment double charged', 'description' => 'Customer #4411 charged twice.', 'priority' => 'urgent'];

    /**
     * The twelve requests of the standard traffic scenario (app:simulate-traffic), in order,
     * same list as FXUrsNAcceptanceSweepTest::SCENARIO: [method, uri, body|null].
     */
    private const array SCENARIO = [
        ['GET', '/api/stats', null],
        ['POST', '/api/tickets', ['title' => 'Login page shows blank screen', 'description' => 'After the 2.3 deploy, Safari users see nothing.', 'priority' => 'high']],
        ['POST', '/api/tickets', ['title' => 'Typo on pricing page', 'description' => 'Recieve should be Receive.', 'priority' => 'low']],
        ['POST', '/api/tickets', ['title' => 'Export CSV is empty', 'description' => 'Exported file has headers only.']],
        ['POST', '/api/tickets', ['title' => 'Payment double charged', 'description' => 'Customer #4411 charged twice.', 'priority' => 'urgent']],
        ['GET', '/api/tickets', null],
        ['GET', '/api/tickets/1', null],
        ['GET', '/api/tickets/9999', null],
        ['PATCH', '/api/tickets/1/status', ['status' => 'in_progress']],
        ['PATCH', '/api/tickets/1/status', ['status' => 'done']],
        ['GET', '/api/tickets?status=open', null],
        ['GET', '/api/stats', null],
    ];

    private const array EXPECTED_STATUSES = [200, 201, 201, 422, 201, 200, 200, 404, 200, 422, 200, 200];

    private function at(string $modifier): \DateTimeImmutable
    {
        return new \DateTimeImmutable($modifier);
    }

    private function assertJsonContentType(string $message = ''): void
    {
        self::assertStringStartsWith('application/json', (string) $this->client->getResponse()->headers->get('Content-Type'), $message);
    }

    private function assertNonEmptyString(mixed $value, string $message = ''): void
    {
        self::assertIsString($value, $message);
        self::assertNotSame('', $value, $message);
    }

    private function assertTicketCount(int $expected, string $message = ''): void
    {
        $this->client->request('GET', '/api/tickets');
        self::assertResponseStatusCodeSame(200, $message);
        self::assertSame($expected, $this->body()['count'], $message);
    }

    /** @param array<string, mixed> $ticket */
    private static function dueMinusCreatedSeconds(array $ticket): int
    {
        self::assertIsString($ticket['createdAt']);
        self::assertIsString($ticket['dueAt']);

        return (new \DateTimeImmutable($ticket['dueAt']))->getTimestamp() - (new \DateTimeImmutable($ticket['createdAt']))->getTimestamp();
    }

    /** @param array<string, mixed> $entry */
    private static function assertZeroRow(array $entry, string $priority): void
    {
        self::assertSame(self::ENTRY_KEYS, array_keys($entry), $priority);
        self::assertSame($priority, $entry['priority']);
        self::assertSame(0, $entry['total'], $priority);
        self::assertSame(0, $entry['breached'], $priority);
        self::assertEquals(0.0, $entry['breach_rate'], $priority);
    }

    /**
     * AC1 (URS-F-03.1, D1): POST with priority urgent is a 201 JSON ticket with the eight
     * keys, priority urgent, status open, no assignee, and dueAt = createdAt + 2 h (7200 s).
     */
    public function testAc1UrsF0301UrgentIsAcceptedWithTwoHourDueAt(): void
    {
        $this->postJson('/api/tickets', self::URGENT_PAYLOAD);

        self::assertResponseStatusCodeSame(201);
        $this->assertJsonContentType();
        $body = $this->body();
        self::assertSame(self::TICKET_KEYS, array_keys($body));
        self::assertSame('urgent', $body['priority']);
        self::assertSame('open', $body['status']);
        self::assertNull($body['assignee']);
        self::assertSame(7200, self::dueMinusCreatedSeconds($body));
    }

    /**
     * AC2 (D1, D2): the Ticket constants carry urgent = 2 h after high, the three existing
     * targets unchanged.
     */
    public function testAc2D1D2SlaTargetHoursAndPrioritiesConstants(): void
    {
        self::assertSame(2, Ticket::slaTargetHours('urgent'));
        self::assertSame(2, Ticket::SLA_TARGET_HOURS['urgent'] ?? null);
        self::assertSame(['low', 'normal', 'high', 'urgent'], Ticket::PRIORITIES);

        self::assertSame(72, Ticket::SLA_TARGET_HOURS['low'] ?? null);
        self::assertSame(24, Ticket::SLA_TARGET_HOURS['normal'] ?? null);
        self::assertSame(8, Ticket::SLA_TARGET_HOURS['high'] ?? null);
    }

    /**
     * AC3 (URS-F-03.2): every string outside the four values still falls back to the
     * normal 24 h target; the default priority stays normal.
     */
    public function testAc3UrsF0302UnknownStringsStillFallBackToNormalTarget(): void
    {
        foreach (['', 'LOW', 'Urgent', 'URGENT', 'critical'] as $value) {
            self::assertSame(24, Ticket::slaTargetHours($value), 'slaTargetHours('.var_export($value, true).')');
        }

        self::assertSame('normal', Ticket::SLA_DEFAULT_PRIORITY);
    }

    /**
     * AC4 (URS-N-02): any priority that is not exactly one of the four values (case
     * variants, unknown strings, empty, null, number, missing key) is a JSON 422 keyed on
     * priority only, and nothing is stored.
     */
    public function testAc4UrsN02EverythingElseIsRejectedCaseSensitively(): void
    {
        $payloads = [];
        foreach (['Urgent', 'URGENT', 'High', 'critical', '', null, 123] as $value) {
            $payloads['priority='.var_export($value, true)] = ['title' => 'Payment double charged', 'description' => 'Customer #4411 charged twice.', 'priority' => $value];
        }
        $payloads['missing priority key'] = ['title' => 'Payment double charged', 'description' => 'Customer #4411 charged twice.'];

        foreach ($payloads as $label => $payload) {
            $this->postJson('/api/tickets', $payload);

            self::assertResponseStatusCodeSame(422, $label);
            $this->assertJsonContentType($label);
            $body = $this->body();
            self::assertSame(['errors'], array_keys($body), $label);
            self::assertSame(['priority'], array_keys($body['errors']), $label);
            $this->assertNonEmptyString($body['errors']['priority'], $label);
        }

        $this->assertTicketCount(0);
    }

    /**
     * AC5 (URS-F-05.2, D2): on an empty table the report has exactly four rows, low, normal,
     * high, urgent, no other row, and the urgent row (fourth) is all zero.
     */
    public function testAc5UrsF0502EmptyReportListsUrgentRowFourthWithZeros(): void
    {
        $this->client->request('GET', '/api/reports/sla');

        self::assertResponseStatusCodeSame(200);
        $this->assertJsonContentType();
        $body = $this->body();
        self::assertSame(self::ENVELOPE_KEYS, array_keys($body));
        $priorities = $body['priorities'];
        self::assertCount(4, $priorities);
        self::assertSame(['low', 'normal', 'high', 'urgent'], array_column($priorities, 'priority'));
        self::assertNotContains('other', array_column($priorities, 'priority'));

        $urgent = $priorities[3];
        self::assertSame(self::ENTRY_KEYS, array_keys($urgent));
        self::assertSame('urgent', $urgent['priority']);
        self::assertSame(0, $urgent['total']);
        self::assertSame(0, $urgent['breached']);
        self::assertEquals(0.0, $urgent['breach_rate']);
    }

    /**
     * AC6 (URS-F-05.1, D2): urgent tickets are counted in their own row (fourth) by the
     * common breach rules; a priority outside the four values still lands in a trailing
     * other row; the breached filter agrees with the report.
     */
    public function testAc6UrsF0501UrgentRowIsComputedAndOtherStaysTrailing(): void
    {
        $this->createTicket(title: 'Urgent breached', priority: 'urgent', status: 'open', createdAt: $this->at('-3 hours'), dueAt: $this->at('-1 hour'));
        $this->createTicket(title: 'Urgent fine', priority: 'urgent', status: 'open', createdAt: $this->at('-1 hour'), dueAt: $this->at('+1 hour'));
        $this->createTicket(title: 'Urgent closed past due', priority: 'urgent', status: 'closed', createdAt: $this->at('-3 hours'), dueAt: $this->at('-1 hour'));
        $this->createTicket(title: 'Escalated breached', priority: 'escalated', status: 'open', createdAt: $this->at('-3 hours'), dueAt: $this->at('-1 hour'));

        $this->client->request('GET', '/api/reports/sla');

        self::assertResponseStatusCodeSame(200);
        $priorities = $this->body()['priorities'];
        self::assertSame(['low', 'normal', 'high', 'urgent', 'other'], array_column($priorities, 'priority'));

        $urgent = $priorities[3];
        self::assertSame(self::ENTRY_KEYS, array_keys($urgent));
        self::assertSame('urgent', $urgent['priority']);
        self::assertSame(3, $urgent['total']);
        self::assertSame(1, $urgent['breached']);
        self::assertEquals(33.3, $urgent['breach_rate']);

        self::assertZeroRow($priorities[0], 'low');
        self::assertZeroRow($priorities[1], 'normal');
        self::assertZeroRow($priorities[2], 'high');

        self::assertSame(['priority' => 'other', 'total' => 1, 'breached' => 1, 'breach_rate' => 100.0], $priorities[4]);

        $this->client->request('GET', '/api/tickets?breached=true');
        self::assertResponseStatusCodeSame(200);
        self::assertSame(2, $this->body()['count']);
    }

    /**
     * AC7 (URS-N-03): every ticket endpoint keeps its JSON shape for an urgent ticket; the
     * status filter and GET /api/stats are unchanged.
     */
    public function testAc7UrsN03EndpointsKeepTheirShapeForUrgentTicket(): void
    {
        $this->postJson('/api/tickets', self::URGENT_PAYLOAD);
        self::assertResponseStatusCodeSame(201);
        $id = $this->body()['id'];

        $this->client->request('GET', '/api/tickets');
        self::assertResponseStatusCodeSame(200, 'list');
        $list = $this->body();
        self::assertSame(1, $list['count'], 'list');
        self::assertSame('urgent', $list['items'][0]['priority'], 'list');

        $this->client->request('GET', "/api/tickets/{$id}");
        self::assertResponseStatusCodeSame(200, 'show');
        self::assertSame(self::TICKET_KEYS, array_keys($this->body()), 'show');
        self::assertSame('urgent', $this->body()['priority'], 'show');

        $this->patchJson("/api/tickets/{$id}/status", ['status' => 'in_progress']);
        self::assertResponseStatusCodeSame(200, 'status');
        self::assertSame(self::TICKET_KEYS, array_keys($this->body()), 'status');
        self::assertSame('urgent', $this->body()['priority'], 'status');

        $this->patchJson("/api/tickets/{$id}/assignee", ['agentId' => null]);
        self::assertResponseStatusCodeSame(200, 'assignee');
        self::assertSame(self::TICKET_KEYS, array_keys($this->body()), 'assignee');
        self::assertSame('urgent', $this->body()['priority'], 'assignee');

        $this->client->request('GET', '/api/tickets?status=in_progress');
        self::assertResponseStatusCodeSame(200, 'status filter');
        self::assertSame(1, $this->body()['count'], 'status filter');

        $this->client->request('GET', '/api/stats');
        self::assertResponseStatusCodeSame(200, 'stats');
        $stats = $this->body();
        self::assertSame(self::STATS_KEYS, array_keys($stats), 'stats');
        self::assertSame(1, $stats['total'], 'stats');
    }

    /**
     * AC8 (URS-F-03.3): urgent rows stored before this item keep their stored dueAt
     * (createdAt + 24 h for rows created between F0 and FX, NULL for rows migrated by F0),
     * nothing is recomputed; they are counted in the urgent row and listed unchanged.
     */
    public function testAc8UrsF0303ExistingUrgentRowsKeepTheirStoredDueAt(): void
    {
        $t0 = new \DateTimeImmutable('2026-02-01T09:00:00+00:00');

        // Shape of a row created between F0 and FX: urgent fell back to the normal 24 h target.
        $legacy24h = $this->createTicket(title: 'Urgent before R2', description: 'd', priority: 'urgent', createdAt: $t0, dueAt: $t0->modify('+24 hours'));
        $legacy24hId = $legacy24h->getId();

        // Shape of a row migrated by F0: due_at NULL; the constructor always derives dueAt, so insert it raw.
        $connection = $this->em()->getConnection();
        $connection->insert('ticket', [
            'title' => 'Urgent migrated by F0',
            'description' => 'd',
            'priority' => 'urgent',
            'status' => 'open',
            'created_at' => $t0->format('Y-m-d H:i:s'),
            'due_at' => null,
            'assignee_id' => null,
        ]);
        $migratedId = (int) $connection->lastInsertId();

        $this->em()->flush();
        $this->em()->clear();

        $reloaded24h = $this->em()->find(Ticket::class, $legacy24hId);
        self::assertInstanceOf(Ticket::class, $reloaded24h);
        self::assertEquals($t0->modify('+24 hours'), $reloaded24h->getDueAt());
        self::assertNotEquals($t0->modify('+2 hours'), $reloaded24h->getDueAt());

        $reloadedMigrated = $this->em()->find(Ticket::class, $migratedId);
        self::assertInstanceOf(Ticket::class, $reloadedMigrated);
        self::assertNull($reloadedMigrated->getDueAt());
        self::assertNotEquals($t0->modify('+2 hours'), $reloadedMigrated->getDueAt());

        $this->client->request('GET', '/api/reports/sla');
        self::assertResponseStatusCodeSame(200);
        $priorities = $this->body()['priorities'];
        self::assertSame(['low', 'normal', 'high', 'urgent'], array_column($priorities, 'priority'));
        $urgent = $priorities[3];
        self::assertSame('urgent', $urgent['priority']);
        self::assertSame(2, $urgent['total']);
        self::assertSame(1, $urgent['breached']);

        $this->client->request('GET', '/api/tickets');
        self::assertResponseStatusCodeSame(200);
        $list = $this->body();
        self::assertSame(2, $list['count']);
        self::assertSame(['urgent', 'urgent'], array_column($list['items'], 'priority'));
    }

    /**
     * AC9 (section 6, URS-N-02): the standard scenario replayed through the test client yields
     * the new status sequence (fifth request 201, priority urgent), every response JSON; the
     * app:simulate-traffic --reset command exits 0 with HTTP 201: 3, HTTP 422: 2 and no 5xx.
     */
    public function testAc9Section6UrsN02ScenarioReplayAndConsoleCounts(): void
    {
        $statuses = [];
        $fifth = null;
        foreach (self::SCENARIO as $i => [$method, $uri, $payload]) {
            $this->client->request(
                $method,
                $uri,
                server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
                content: null === $payload ? null : json_encode($payload, JSON_THROW_ON_ERROR),
            );
            $label = sprintf('request #%d %s %s', $i + 1, $method, $uri);
            $statuses[] = $this->client->getResponse()->getStatusCode();

            $this->assertJsonContentType($label);
            $decoded = $this->body();
            self::assertIsArray($decoded, $label);
            if (4 === $i) {
                $fifth = $decoded;
            }
        }

        self::assertSame(self::EXPECTED_STATUSES, $statuses);
        self::assertIsArray($fifth);
        self::assertSame('urgent', $fifth['priority'] ?? null, 'fifth response');

        $application = new Application(static::$kernel);
        $application->setAutoExit(false);
        $tester = new CommandTester($application->find('app:simulate-traffic'));

        $tester->execute(['--reset' => true]);

        // Console output carries the platform's EOL (CRLF on Windows); normalise so `$` in /m mode matches.
        $display = str_replace("\r\n", "\n", $tester->getDisplay());
        self::assertSame(0, $tester->getStatusCode(), $display);
        self::assertMatchesRegularExpression('/^HTTP 201: 3$/m', $display);
        self::assertMatchesRegularExpression('/^HTTP 422: 2$/m', $display);
        self::assertDoesNotMatchRegularExpression('/-> 5\d\d$/m', $display);
    }
}
