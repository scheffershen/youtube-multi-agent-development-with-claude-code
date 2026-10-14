<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Agent;
use App\Entity\Ticket;
use Monolog\Handler\TestHandler;
use Monolog\LogRecord;
use Psr\Log\LoggerInterface;

/**
 * Spec: specs/U2-agent-active-flag.md (URS-002 SLA module, URS-F-01.4 / URS-N-01 / URS-N-02).
 *
 * One test method per acceptance criterion, written from the spec only.
 * AC18 is a service-level check through the entity manager but runs in this class as the spec asks.
 */
final class U2AgentActiveFlagTest extends ApiTestCase
{
    private const array AGENT_KEYS = ['id', 'name', 'email', 'active'];

    private const array TICKET_KEYS = ['id', 'title', 'description', 'priority', 'status', 'createdAt', 'dueAt', 'assignee'];

    private const array STATS_KEYS = ['total', 'open', 'in_progress', 'closed', 'closed_ratio'];

    private const string DUPLICATE_EMAIL_MESSAGE = 'An agent with this email already exists.';

    private const string LOG_MESSAGE = 'Agent activation changed';

    private function contentType(): string
    {
        return (string) $this->client->getResponse()->headers->get('Content-Type');
    }

    private function assertJsonContentType(string $message = ''): void
    {
        self::assertStringStartsWith('application/json', $this->contentType(), $message);
    }

    private function uri(Agent $agent): string
    {
        return '/api/agents/'.$agent->getId();
    }

    /** @return array<string, mixed> */
    private function listAgents(): array
    {
        $this->client->request('GET', '/api/agents');
        self::assertResponseStatusCodeSame(200);

        return $this->body();
    }

    /** Asserts a 422 whose body is exactly {"errors": {"active": "<non-empty>"}}. */
    private function assertActiveValidationError(string $message = ''): void
    {
        self::assertResponseStatusCodeSame(422, $message);
        $this->assertJsonContentType($message);

        $body = $this->body();
        self::assertSame(['errors'], array_keys($body), $message);
        self::assertIsArray($body['errors'], $message);
        self::assertSame(['active'], array_keys($body['errors']), $message);
        self::assertIsString($body['errors']['active'], $message);
        self::assertNotSame('', $body['errors']['active'], $message);
    }

    /** Asserts a JSON response whose `error` key is a non-empty string. */
    private function assertErrorString(string $message = ''): void
    {
        $this->assertJsonContentType($message);

        $body = $this->body();
        self::assertArrayHasKey('error', $body, $message);
        self::assertIsString($body['error'], $message);
        self::assertNotSame('', $body['error'], $message);
    }

    private function captureLog(): TestHandler
    {
        $logger = static::getContainer()->get(LoggerInterface::class);
        $handler = new TestHandler();
        $logger->pushHandler($handler);

        return $handler;
    }

    /** @param array{id: int, active: bool} $context */
    private function assertActivationLogged(TestHandler $handler, array $context): void
    {
        self::assertTrue(
            $handler->hasInfoThatPasses(static fn (LogRecord $r): bool => self::LOG_MESSAGE === $r->message && $context === $r->context),
            'expected one info record "'.self::LOG_MESSAGE.'" with context '.json_encode($context),
        );

        $matching = array_filter($handler->getRecords(), static fn (LogRecord $r): bool => self::LOG_MESSAGE === $r->message);
        self::assertCount(1, $matching, 'exactly one record with message "'.self::LOG_MESSAGE.'"');
    }

    /**
     * AC1 (URS-F-01.4): deactivating an active agent returns 200 with exactly Agent::toArray().
     */
    public function testAc1UrsF0104DeactivateReturns200WithAgentShape(): void
    {
        $agent = $this->createAgent('Ada', 'ada@example.test', true);

        $this->patchJson($this->uri($agent), ['active' => false]);

        self::assertResponseStatusCodeSame(200);
        $this->assertJsonContentType();

        $body = $this->body();
        self::assertSame(self::AGENT_KEYS, array_keys($body));
        self::assertSame(['id' => $agent->getId(), 'name' => 'Ada', 'email' => 'ada@example.test', 'active' => false], $body);
    }

    /**
     * AC2 (URS-F-01.4): reactivating an inactive agent returns 200 with active true and name/email untouched.
     */
    public function testAc2UrsF0104ReactivateReturns200(): void
    {
        $agent = $this->createAgent('Zed', 'zed@example.test', false);

        $this->patchJson($this->uri($agent), ['active' => true]);

        self::assertResponseStatusCodeSame(200);
        $this->assertJsonContentType();

        $body = $this->body();
        self::assertTrue($body['active']);
        self::assertSame('Zed', $body['name']);
        self::assertSame('zed@example.test', $body['email']);
    }

    /**
     * AC3 (URS-F-01.4): the change is persisted and the list ordering (active first, then id) reflects it.
     */
    public function testAc3UrsF0104ChangeIsPersistedAndReflectedInListOrder(): void
    {
        $ann = $this->createAgent('Ann', 'ann@example.test', true);
        $this->createAgent('Bea', 'bea@example.test', true);

        $this->patchJson($this->uri($ann), ['active' => false]);
        self::assertResponseStatusCodeSame(200);

        $list = $this->listAgents();
        self::assertSame(2, $list['count']);
        self::assertSame(['Bea', 'Ann'], array_column($list['items'], 'name'));
        self::assertSame([true, false], array_column($list['items'], 'active'));
    }

    /**
     * AC4 (URS-F-01.4): a no-op is a 200 and name/email/id keys in the body are ignored.
     */
    public function testAc4UrsF0104NoOpReturns200AndExtraKeysAreIgnored(): void
    {
        $agent = $this->createAgent('Ada', 'ada@example.test', true);

        $this->patchJson($this->uri($agent), ['active' => true, 'name' => 'Mallory', 'email' => 'm@example.test', 'id' => 9999]);

        self::assertResponseStatusCodeSame(200);
        $this->assertJsonContentType();
        self::assertSame(['id' => $agent->getId(), 'name' => 'Ada', 'email' => 'ada@example.test', 'active' => true], $this->body());

        $list = $this->listAgents();
        self::assertSame(1, $list['count']);
        self::assertSame('Ada', $list['items'][0]['name']);
    }

    /**
     * AC5 (URS-N-02): a missing `active` key ({} and {"other": 1}) is a JSON 422 keyed on active; agent unchanged.
     */
    public function testAc5UrsN02MissingActiveReturns422(): void
    {
        $agent = $this->createAgent();

        $this->client->request('PATCH', $this->uri($agent), server: ['CONTENT_TYPE' => 'application/json'], content: '{}');
        $this->assertActiveValidationError('case {}');

        $this->patchJson($this->uri($agent), ['other' => 1]);
        $this->assertActiveValidationError('case {"other": 1}');

        self::assertTrue($this->listAgents()['items'][0]['active']);
    }

    /**
     * AC6 (URS-N-02): a non-boolean `active` is a JSON 422 keyed on active, never 500; agent unchanged.
     */
    public function testAc6UrsN02NonBooleanActiveReturns422(): void
    {
        $agent = $this->createAgent();

        $bodies = [
            ['active' => null],
            ['active' => 'yes'],
            ['active' => 'true'],
            ['active' => ''],
            ['active' => 1],
            ['active' => 0],
            ['active' => []],
        ];

        foreach ($bodies as $i => $payload) {
            $this->patchJson($this->uri($agent), $payload);
            $this->assertActiveValidationError("case #$i ".json_encode($payload));
        }

        self::assertTrue($this->listAgents()['items'][0]['active']);
    }

    /**
     * AC7 (URS-N-02): an unknown numeric id is a JSON 404 with exactly {"error": "Agent not found"}.
     */
    public function testAc7UrsN02UnknownIdReturns404(): void
    {
        $this->patchJson('/api/agents/9999', ['active' => false]);

        self::assertResponseStatusCodeSame(404);
        $this->assertJsonContentType();
        self::assertSame(['error' => 'Agent not found'], $this->body());
    }

    /**
     * AC8 (URS-N-02): payload validation runs before the lookup, so unknown id + invalid body is a 422.
     */
    public function testAc8UrsN02ValidationPrecedesLookup(): void
    {
        $this->patchJson('/api/agents/9999', ['active' => 'yes']);

        self::assertResponseStatusCodeSame(422);
        $this->assertJsonContentType();

        $body = $this->body();
        self::assertArrayHasKey('errors', $body);
        self::assertArrayHasKey('active', $body['errors']);
        self::assertIsString($body['errors']['active']);
        self::assertNotSame('', $body['errors']['active']);
    }

    /**
     * AC9 (URS-N-02): a non-numeric id is a JSON 404 with an error string, not a 500.
     */
    public function testAc9UrsN02NonNumericIdReturnsJson404(): void
    {
        $this->patchJson('/api/agents/abc', ['active' => false]);

        self::assertResponseStatusCodeSame(404);
        $this->assertErrorString();
    }

    /**
     * AC10 (URS-N-02): GET on /api/agents/{id} is a JSON 405 with an Allow header containing PATCH.
     */
    public function testAc10UrsN02WrongMethodReturnsJson405WithAllowHeader(): void
    {
        $agent = $this->createAgent();

        $this->client->request('GET', $this->uri($agent));

        self::assertResponseStatusCodeSame(405);
        $this->assertErrorString();
        self::assertStringContainsString('PATCH', (string) $this->client->getResponse()->headers->get('Allow'));
    }

    /**
     * AC11 (URS-N-02): malformed JSON and an empty body are a JSON 400 with an error string; agent unchanged.
     */
    public function testAc11UrsN02MalformedAndEmptyBodyReturnJson400(): void
    {
        $agent = $this->createAgent();

        foreach (['{not json', ''] as $i => $content) {
            $this->client->request('PATCH', $this->uri($agent), server: ['CONTENT_TYPE' => 'application/json'], content: $content);

            self::assertResponseStatusCodeSame(400, "case #$i");
            $this->assertErrorString("case #$i");
        }

        self::assertTrue($this->listAgents()['items'][0]['active']);
    }

    /**
     * AC12 (URS-N-02): an unsupported content type is a JSON 415 with an error string; agent unchanged.
     */
    public function testAc12UrsN02UnsupportedContentTypeReturnsJson415(): void
    {
        $agent = $this->createAgent();

        $this->client->request('PATCH', $this->uri($agent), server: ['CONTENT_TYPE' => 'text/plain'], content: '{"active":false}');

        self::assertResponseStatusCodeSame(415);
        $this->assertErrorString();

        self::assertTrue($this->listAgents()['items'][0]['active']);
    }

    /**
     * AC13 (URS-N-01): deactivation writes exactly one info record "Agent activation changed" with {id, active: false}.
     */
    public function testAc13UrsN01DeactivationIsLoggedAtInfo(): void
    {
        $agent = $this->createAgent();
        $handler = $this->captureLog();

        $this->patchJson($this->uri($agent), ['active' => false]);
        self::assertResponseStatusCodeSame(200);

        $this->assertActivationLogged($handler, ['id' => $agent->getId(), 'active' => false]);
    }

    /**
     * AC14 (URS-N-01): activation writes an info record "Agent activation changed" with {id, active: true}.
     */
    public function testAc14UrsN01ActivationIsLoggedAtInfo(): void
    {
        $agent = $this->createAgent('Zed', 'zed@example.test', false);
        $handler = $this->captureLog();

        $this->patchJson($this->uri($agent), ['active' => true]);
        self::assertResponseStatusCodeSame(200);

        $this->assertActivationLogged($handler, ['id' => $agent->getId(), 'active' => true]);
    }

    /**
     * AC15 (URS-N-01): a no-op is still logged, with the unchanged value.
     */
    public function testAc15UrsN01NoOpIsLoggedWithUnchangedValue(): void
    {
        $agent = $this->createAgent();
        $handler = $this->captureLog();

        $this->patchJson($this->uri($agent), ['active' => true]);
        self::assertResponseStatusCodeSame(200);

        $this->assertActivationLogged($handler, ['id' => $agent->getId(), 'active' => true]);
    }

    /**
     * AC16 (URS-N-01): rejected requests (404, 422, 415) write no "Agent activation changed" record.
     */
    public function testAc16UrsN01RejectedRequestsAreNotLogged(): void
    {
        $agent = $this->createAgent();
        $this->client->disableReboot();
        $handler = $this->captureLog();

        $this->patchJson('/api/agents/9999', ['active' => false]);
        self::assertResponseStatusCodeSame(404);

        $this->patchJson($this->uri($agent), ['active' => 'yes']);
        self::assertResponseStatusCodeSame(422);

        $this->client->request('PATCH', $this->uri($agent), server: ['CONTENT_TYPE' => 'text/plain'], content: '{"active":false}');
        self::assertResponseStatusCodeSame(415);

        self::assertFalse($handler->hasInfoThatContains(self::LOG_MESSAGE));
    }

    /**
     * AC17: existing endpoints keep their status codes and JSON bodies.
     */
    public function testAc17ExistingEndpointsUnchanged(): void
    {
        $this->postJson('/api/agents', ['name' => 'Ada', 'email' => 'ada@example.test']);
        self::assertResponseStatusCodeSame(201);
        self::assertSame(self::AGENT_KEYS, array_keys($this->body()));

        $this->postJson('/api/agents', ['name' => 'Ada', 'email' => 'ada@example.test']);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(['errors' => ['email' => self::DUPLICATE_EMAIL_MESSAGE]], $this->body());

        $this->client->request('POST', '/api/agents', server: ['CONTENT_TYPE' => 'application/json'], content: '{}');
        self::assertResponseStatusCodeSame(422);
        $errors = $this->body()['errors'];
        self::assertArrayHasKey('name', $errors);
        self::assertArrayHasKey('email', $errors);

        $this->postJson('/api/tickets', ['title' => 'T', 'description' => 'd', 'priority' => 'low']);
        self::assertResponseStatusCodeSame(201);
        $ticket = $this->body();
        self::assertSame(self::TICKET_KEYS, array_keys($ticket));

        $this->patchJson('/api/tickets/'.$ticket['id'].'/status', ['status' => 'done']);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('Invalid status', $this->body()['error']);

        $this->client->request('GET', '/api/stats');
        self::assertResponseStatusCodeSame(200);
        self::assertSame(self::STATS_KEYS, array_keys($this->body()));
    }

    /**
     * AC18 (URS-F-01.4): deactivating an agent does not unassign its tickets (service level, via the entity manager).
     */
    public function testAc18UrsF0104DeactivationDoesNotUnassignTickets(): void
    {
        $agent = $this->createAgent();
        $ticket = $this->createTicket(assignee: $agent);

        $this->patchJson($this->uri($agent), ['active' => false]);
        self::assertResponseStatusCodeSame(200);

        $this->em()->clear();
        $reloaded = $this->em()->find(Ticket::class, $ticket->getId());
        self::assertInstanceOf(Ticket::class, $reloaded);

        $assignee = $reloaded->getAssignee();
        self::assertInstanceOf(Agent::class, $assignee);
        self::assertSame($agent->getId(), $assignee->getId());
        self::assertFalse($assignee->isActive());
    }
}
