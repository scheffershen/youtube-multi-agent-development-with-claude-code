<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Ticket;
use Monolog\Handler\TestHandler;
use Monolog\LogRecord;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Spec: specs/FX-urs-n-acceptance-sweep.md (URS-002 SLA module, sections 5 and 6:
 * URS-N-01 audit log, URS-N-02 JSON errors / no 500, URS-N-03 backward compatibility).
 *
 * One test method per acceptance criterion, written from the spec only.
 * AC13 is the amendment of F0SlaFoundationTest::testAc11UrsF0302UnknownPriorityFallsBackToNormalTarget
 * and lives in that file.
 */
final class FXUrsNAcceptanceSweepTest extends ApiTestCase
{
    private const array TICKET_KEYS = ['id', 'title', 'description', 'priority', 'status', 'createdAt', 'dueAt', 'assignee'];

    private const array STATS_KEYS = ['total', 'open', 'in_progress', 'closed', 'closed_ratio'];

    private const string NOT_FOUND_MESSAGE = 'Ticket not found';

    private const string ASSIGNEE_LOG_MESSAGE = 'Ticket assignee changed';

    private const string ACTIVATION_LOG_MESSAGE = 'Agent activation changed';

    /**
     * The twelve requests of the standard traffic scenario (app:simulate-traffic), in order:
     * [method, uri, body|null]. The expected status per request is in EXPECTED_STATUSES.
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

    private const array EXPECTED_STATUSES = [200, 201, 201, 422, 422, 200, 200, 404, 200, 422, 200, 200];

    private function contentType(): string
    {
        return (string) $this->client->getResponse()->headers->get('Content-Type');
    }

    private function assertJsonContentType(string $message = ''): void
    {
        self::assertStringStartsWith('application/json', $this->contentType(), $message);
    }

    private function assertNonEmptyString(mixed $value, string $message = ''): void
    {
        self::assertIsString($value, $message);
        self::assertNotSame('', $value, $message);
    }

    private function assertTicketCount(int $expected): void
    {
        $this->client->request('GET', '/api/tickets');
        self::assertResponseStatusCodeSame(200);
        self::assertSame($expected, $this->body()['count']);
    }

    /** @param array<string, mixed> $body */
    private function assertErrorsOnlyOn(array $body, string $field, string $message = ''): void
    {
        self::assertSame(['errors'], array_keys($body), $message);
        self::assertSame([$field], array_keys($body['errors']), $message);
        $this->assertNonEmptyString($body['errors'][$field], $message);
    }

    private function attachLogHandler(): TestHandler
    {
        $this->client->disableReboot();
        $logger = static::getContainer()->get(LoggerInterface::class);
        $handler = new TestHandler();
        $logger->pushHandler($handler);

        return $handler;
    }

    /**
     * AC1 (URS-N-02): the scenario's POST without priority is a JSON 422 keyed on priority only,
     * and nothing is persisted.
     */
    public function testAc1UrsN02MissingPriorityReturns422(): void
    {
        $this->postJson('/api/tickets', ['title' => 'Export CSV is empty', 'description' => 'Exported file has headers only.']);

        self::assertResponseStatusCodeSame(422);
        $this->assertJsonContentType();
        $body = $this->body();
        self::assertSame(['errors'], array_keys($body));
        $this->assertNonEmptyString($body['errors']['priority'] ?? null);
        self::assertArrayNotHasKey('title', $body['errors']);
        self::assertArrayNotHasKey('description', $body['errors']);

        $this->assertTicketCount(0);
    }

    /**
     * AC2 (URS-N-02): every priority outside low|normal|high (including urgent) is a 422 on
     * priority; nothing is stored; the priority list is not extended.
     */
    public function testAc2UrsN02UnknownPriorityReturns422AndUrgentIsNotAdded(): void
    {
        foreach (['urgent', 'High', 'LOW', '', null, 123] as $value) {
            $label = 'priority='.var_export($value, true);

            $this->postJson('/api/tickets', ['title' => 'Payment double charged', 'description' => 'Customer #4411 charged twice.', 'priority' => $value]);

            self::assertResponseStatusCodeSame(422, $label);
            $this->assertJsonContentType($label);
            $this->assertErrorsOnlyOn($this->body(), 'priority', $label);
        }

        $this->assertTicketCount(0);
        self::assertSame(['low', 'normal', 'high'], Ticket::PRIORITIES);
    }

    /**
     * AC3 (URS-N-02): invalid title or description is a 422 keyed on that field; an empty
     * object reports all three fields; nothing is stored.
     */
    public function testAc3UrsN02InvalidTitleAndDescriptionReturn422(): void
    {
        $titleBodies = [
            'no title' => ['description' => 'd', 'priority' => 'low'],
            'empty title' => ['title' => '', 'description' => 'd', 'priority' => 'low'],
            'blank title' => ['title' => '   ', 'description' => 'd', 'priority' => 'low'],
            'title too long' => ['title' => str_repeat('x', 121), 'description' => 'd', 'priority' => 'low'],
            'non-string title' => ['title' => 123, 'description' => 'd', 'priority' => 'low'],
        ];
        foreach ($titleBodies as $label => $payload) {
            $this->postJson('/api/tickets', $payload);

            self::assertResponseStatusCodeSame(422, $label);
            $this->assertJsonContentType($label);
            $body = $this->body();
            self::assertArrayHasKey('errors', $body, $label);
            $this->assertNonEmptyString($body['errors']['title'] ?? null, $label);
            self::assertArrayNotHasKey('priority', $body['errors'], $label);
        }

        $descriptionBodies = [
            'no description' => ['title' => 'T', 'priority' => 'low'],
            'empty description' => ['title' => 'T', 'description' => '', 'priority' => 'low'],
            'null description' => ['title' => 'T', 'description' => null, 'priority' => 'low'],
        ];
        foreach ($descriptionBodies as $label => $payload) {
            $this->postJson('/api/tickets', $payload);

            self::assertResponseStatusCodeSame(422, $label);
            $this->assertJsonContentType($label);
            $body = $this->body();
            self::assertArrayHasKey('errors', $body, $label);
            $this->assertNonEmptyString($body['errors']['description'] ?? null, $label);
            self::assertArrayNotHasKey('priority', $body['errors'], $label);
        }

        $this->client->request('POST', '/api/tickets', server: ['CONTENT_TYPE' => 'application/json'], content: '{}');

        self::assertResponseStatusCodeSame(422, 'empty object');
        $this->assertJsonContentType('empty object');
        $body = $this->body();
        self::assertArrayHasKey('errors', $body);
        self::assertArrayHasKey('title', $body['errors']);
        self::assertArrayHasKey('description', $body['errors']);
        self::assertArrayHasKey('priority', $body['errors']);

        $this->assertTicketCount(0);
    }

    /**
     * AC4 (URS-N-03): a valid POST still returns 201 with the eight ticket keys and the SLA
     * due time; unknown keys are ignored; a 120-character title is accepted.
     */
    public function testAc4UrsN03ValidCreateIsUnchanged(): void
    {
        $this->postJson('/api/tickets', ['title' => 'Login page shows blank screen', 'description' => 'After the 2.3 deploy, Safari users see nothing.', 'priority' => 'high']);

        self::assertResponseStatusCodeSame(201);
        $this->assertJsonContentType();
        $body = $this->body();
        self::assertSame(self::TICKET_KEYS, array_keys($body));
        self::assertSame('Login page shows blank screen', $body['title']);
        self::assertSame('After the 2.3 deploy, Safari users see nothing.', $body['description']);
        self::assertSame('high', $body['priority']);
        self::assertSame('open', $body['status']);
        self::assertNull($body['assignee']);
        $createdAt = new \DateTimeImmutable($body['createdAt']);
        $dueAt = new \DateTimeImmutable($body['dueAt']);
        self::assertSame(28800, $dueAt->getTimestamp() - $createdAt->getTimestamp());

        $this->postJson('/api/tickets', ['title' => 'Second', 'description' => 'd', 'priority' => 'low', 'colour' => 'blue']);

        self::assertResponseStatusCodeSame(201, 'unknown key');
        self::assertArrayNotHasKey('colour', $this->body());

        $this->postJson('/api/tickets', ['title' => str_repeat('x', 120), 'description' => 'd', 'priority' => 'normal']);

        self::assertResponseStatusCodeSame(201, '120-character title');
    }

    /**
     * AC5 (URS-N-02): malformed JSON and an empty body are JSON 400; a non-JSON content type
     * is JSON 415; nothing is stored.
     */
    public function testAc5UrsN02MalformedJsonIs400AndNonJsonContentTypeIs415(): void
    {
        $this->client->request('POST', '/api/tickets', server: ['CONTENT_TYPE' => 'application/json'], content: '{not json');

        self::assertResponseStatusCodeSame(400, 'malformed JSON');
        $this->assertJsonContentType('malformed JSON');
        $this->assertNonEmptyString($this->body()['error'] ?? null, 'malformed JSON');

        $this->client->request('POST', '/api/tickets', server: ['CONTENT_TYPE' => 'application/json'], content: '');

        self::assertResponseStatusCodeSame(400, 'empty body');
        $this->assertJsonContentType('empty body');
        $this->assertNonEmptyString($this->body()['error'] ?? null, 'empty body');

        $this->client->request('POST', '/api/tickets', server: ['CONTENT_TYPE' => 'text/plain'], content: '{"title":"T","description":"d","priority":"low"}');

        self::assertResponseStatusCodeSame(415, 'text/plain');
        $this->assertJsonContentType('text/plain');
        $this->assertNonEmptyString($this->body()['error'] ?? null, 'text/plain');

        $this->assertTicketCount(0);
    }

    /**
     * AC6 (URS-N-02): an unknown numeric id on GET /api/tickets/{id} is a JSON 404 with the
     * exact "Ticket not found" body; known ids keep returning 200 with the eight keys.
     */
    public function testAc6UrsN02UnknownTicketIdOnShowIsJson404(): void
    {
        $ticket = $this->createTicket();

        $this->client->request('GET', '/api/tickets/9999');

        self::assertResponseStatusCodeSame(404, '/9999');
        $this->assertJsonContentType('/9999');
        self::assertSame(['error' => self::NOT_FOUND_MESSAGE], $this->body());

        $this->client->request('GET', '/api/tickets/0');

        self::assertResponseStatusCodeSame(404, '/0');
        $this->assertJsonContentType('/0');
        self::assertSame(['error' => self::NOT_FOUND_MESSAGE], $this->body());

        $this->client->request('GET', "/api/tickets/{$ticket->getId()}");

        self::assertResponseStatusCodeSame(200, 'existing id');
        self::assertSame(self::TICKET_KEYS, array_keys($this->body()));
    }

    /**
     * AC7 (URS-N-02): a non-numeric id is a JSON 404 and a wrong method is a JSON 405 with an
     * Allow header; never 500.
     */
    public function testAc7UrsN02NonNumericIdAndWrongMethodAreJsonNever500(): void
    {
        $this->client->request('GET', '/api/tickets/abc');

        self::assertResponseStatusCodeSame(404, '/abc');
        $this->assertJsonContentType('/abc');
        $this->assertNonEmptyString($this->body()['error'] ?? null, '/abc');

        $this->client->request('DELETE', '/api/tickets/1');

        self::assertResponseStatusCodeSame(405, 'DELETE');
        $this->assertJsonContentType('DELETE');
        $this->assertNonEmptyString($this->body()['error'] ?? null, 'DELETE');
        self::assertStringContainsString('GET', (string) $this->client->getResponse()->headers->get('Allow'));
    }

    /**
     * AC8 (URS-N-02): GET /api/stats on an empty table is 200 with the five keys, all zero.
     */
    public function testAc8UrsN02StatsOnEmptyTableReturns200WithZeros(): void
    {
        $this->client->request('GET', '/api/stats');

        self::assertResponseStatusCodeSame(200);
        $this->assertJsonContentType();
        $body = $this->body();
        self::assertSame(self::STATS_KEYS, array_keys($body));
        self::assertSame(0, $body['total']);
        self::assertSame(0, $body['open']);
        self::assertSame(0, $body['in_progress']);
        self::assertSame(0, $body['closed']);
        self::assertEquals(0, $body['closed_ratio']);
    }

    /**
     * AC9 (URS-N-03): GET /api/stats with tickets keeps its counts and its one-decimal ratio.
     */
    public function testAc9UrsN03StatsWithTicketsUnchanged(): void
    {
        $this->createTicket(status: 'closed');
        $this->createTicket(status: 'open');
        $this->createTicket(status: 'in_progress');

        $this->client->request('GET', '/api/stats');

        self::assertResponseStatusCodeSame(200);
        $body = $this->body();
        self::assertSame(3, $body['total']);
        self::assertSame(1, $body['open']);
        self::assertSame(1, $body['in_progress']);
        self::assertSame(1, $body['closed']);
        self::assertEquals(33.3, $body['closed_ratio']);
    }

    /**
     * AC10 (section 6): replaying the standard traffic scenario through the test client yields
     * exactly the expected status sequence, every response being decodable JSON; no 500.
     */
    public function testAc10Section6StandardScenarioReplayProducesNo500(): void
    {
        $statuses = [];
        foreach (self::SCENARIO as $i => [$method, $uri, $body]) {
            $this->client->request(
                $method,
                $uri,
                server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
                content: null === $body ? null : json_encode($body, JSON_THROW_ON_ERROR),
            );
            $label = sprintf('request #%d %s %s', $i + 1, $method, $uri);
            $statuses[] = $this->client->getResponse()->getStatusCode();

            $this->assertJsonContentType($label);
            $decoded = $this->body();
            self::assertIsArray($decoded, $label);
        }

        self::assertSame(self::EXPECTED_STATUSES, $statuses);
    }

    /**
     * AC11 (section 6): the app:simulate-traffic --reset console command exits 0 and reports
     * no 5xx, three 422s and one 404.
     */
    public function testAc11Section6ConsoleCommandReportsNo500(): void
    {
        $application = new Application(static::$kernel);
        $application->setAutoExit(false);
        $tester = new CommandTester($application->find('app:simulate-traffic'));

        $tester->execute(['--reset' => true]);

        // Console output carries the platform's EOL (CRLF on Windows); normalise so `$` in /m mode matches.
        $display = str_replace("\r\n", "\n", $tester->getDisplay());
        self::assertSame(0, $tester->getStatusCode(), $display);
        self::assertDoesNotMatchRegularExpression('/-> 5\d\d$/m', $display);
        self::assertDoesNotMatchRegularExpression('/^HTTP 5\d\d:/m', $display);
        self::assertMatchesRegularExpression('/^HTTP 422: 3$/m', $display);
        self::assertMatchesRegularExpression('/^HTTP 404: 1$/m', $display);
    }

    /**
     * AC12 (URS-N-01): assignment, unassignment and activation change each write exactly one
     * info record with the id and the new value, in a single container.
     */
    public function testAc12UrsN01AuditLogSweepAssignUnassignDeactivate(): void
    {
        $agent = $this->createAgent();
        $ticket = $this->createTicket();
        $handler = $this->attachLogHandler();
        $ticketId = $ticket->getId();
        $agentId = $agent->getId();

        $this->patchJson("/api/tickets/{$ticketId}/assignee", ['agentId' => $agentId]);
        self::assertResponseStatusCodeSame(200, 'assign');

        $this->patchJson("/api/tickets/{$ticketId}/assignee", ['agentId' => null]);
        self::assertResponseStatusCodeSame(200, 'unassign');

        $this->patchJson("/api/agents/{$agentId}", ['active' => false]);
        self::assertResponseStatusCodeSame(200, 'deactivate');

        self::assertTrue($handler->hasInfoThatPasses(
            fn (LogRecord $r) => self::ASSIGNEE_LOG_MESSAGE === $r->message
                && ['id' => $ticketId, 'assignee' => $agentId] === $r->context
        ), 'assignment record');
        self::assertTrue($handler->hasInfoThatPasses(
            fn (LogRecord $r) => self::ASSIGNEE_LOG_MESSAGE === $r->message
                && ['id' => $ticketId, 'assignee' => null] === $r->context
        ), 'unassignment record');
        self::assertTrue($handler->hasInfoThatPasses(
            fn (LogRecord $r) => self::ACTIVATION_LOG_MESSAGE === $r->message
                && ['id' => $agentId, 'active' => false] === $r->context
        ), 'activation record');

        $assigneeRecords = array_filter($handler->getRecords(), fn (LogRecord $r) => self::ASSIGNEE_LOG_MESSAGE === $r->message);
        $activationRecords = array_filter($handler->getRecords(), fn (LogRecord $r) => self::ACTIVATION_LOG_MESSAGE === $r->message);
        self::assertCount(2, $assigneeRecords);
        self::assertCount(1, $activationRecords);
    }

    /**
     * AC14 (URS-N-03): every ticket endpoint keeps the eight ticket keys and
     * PATCH /status keeps its exact 422 body. (TicketApiTest.php itself is not edited.)
     */
    public function testAc14UrsN03TicketEndpointsKeepTheirKeysAndStatusBodies(): void
    {
        $this->postJson('/api/tickets', ['title' => 'Printer on fire', 'description' => 'Literally.', 'priority' => 'normal']);
        self::assertResponseStatusCodeSame(201);
        $id = $this->body()['id'];

        $this->client->request('GET', '/api/tickets');
        self::assertResponseStatusCodeSame(200, 'list');
        self::assertSame(self::TICKET_KEYS, array_keys($this->body()['items'][0]), 'list');

        $this->client->request('GET', "/api/tickets/{$id}");
        self::assertResponseStatusCodeSame(200, 'show');
        self::assertSame(self::TICKET_KEYS, array_keys($this->body()), 'show');

        $this->patchJson("/api/tickets/{$id}/status", ['status' => 'closed']);
        self::assertResponseStatusCodeSame(200, 'status');
        self::assertSame(self::TICKET_KEYS, array_keys($this->body()), 'status');

        $this->patchJson("/api/tickets/{$id}/assignee", ['agentId' => null]);
        self::assertResponseStatusCodeSame(200, 'assignee');
        self::assertSame(self::TICKET_KEYS, array_keys($this->body()), 'assignee');

        $this->patchJson("/api/tickets/{$id}/status", ['status' => 'done']);
        self::assertResponseStatusCodeSame(422, 'invalid status');
        self::assertSame(['error' => 'Invalid status', 'allowed' => ['open', 'in_progress', 'closed']], $this->body());
    }
}
