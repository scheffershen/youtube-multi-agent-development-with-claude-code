<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Agent;
use App\Repository\AgentRepository;

/**
 * Spec: specs/U1-agents-create-list.md (URS-002 SLA module, URS-F-01.2 / URS-F-01.3 / URS-N-02).
 *
 * One test method per acceptance criterion, written from the spec only.
 * AC16 is a service-level check on the repository but runs in this class as the spec asks.
 */
final class U1AgentsCreateListTest extends ApiTestCase
{
    private const array AGENT_KEYS = ['id', 'name', 'email', 'active'];

    private const array TICKET_KEYS = ['id', 'title', 'description', 'priority', 'status', 'createdAt', 'dueAt', 'assignee'];

    private const array STATS_KEYS = ['total', 'open', 'in_progress', 'closed', 'closed_ratio'];

    private const string DUPLICATE_EMAIL_MESSAGE = 'An agent with this email already exists.';

    private function contentType(): string
    {
        return (string) $this->client->getResponse()->headers->get('Content-Type');
    }

    private function assertJsonContentType(): void
    {
        self::assertStringStartsWith('application/json', $this->contentType());
    }

    /** @return array<string, mixed> */
    private function listAgents(): array
    {
        $this->client->request('GET', '/api/agents');
        self::assertResponseStatusCodeSame(200);

        return $this->body();
    }

    /**
     * AC1 (URS-F-01.3): POST /api/agents happy path returns 201 JSON with exactly Agent::toArray().
     */
    public function testAc1UrsF0103CreateAgentReturns201WithAgentShape(): void
    {
        $this->postJson('/api/agents', ['name' => 'Ada', 'email' => 'ada@example.test']);

        self::assertResponseStatusCodeSame(201);
        $this->assertJsonContentType();

        $body = $this->body();
        self::assertSame(self::AGENT_KEYS, array_keys($body));
        self::assertIsInt($body['id']);
        self::assertSame('Ada', $body['name']);
        self::assertSame('ada@example.test', $body['email']);
        self::assertTrue($body['active']);
    }

    /**
     * AC2 (URS-F-01.3): the created agent is persisted and listed identically.
     */
    public function testAc2UrsF0103CreatedAgentIsListed(): void
    {
        $this->postJson('/api/agents', ['name' => 'Ada', 'email' => 'ada@example.test']);
        self::assertResponseStatusCodeSame(201);
        $created = $this->body();

        $list = $this->listAgents();
        self::assertSame(1, $list['count']);
        self::assertSame($created, $list['items'][0]);
    }

    /**
     * AC3 (URS-F-01.3): explicit active=false is stored as sent; unknown keys are ignored.
     */
    public function testAc3UrsF0103ActiveFalseStoredAndUnknownKeysIgnored(): void
    {
        $this->postJson('/api/agents', ['name' => 'Bob', 'email' => 'bob@example.test', 'active' => false]);
        self::assertResponseStatusCodeSame(201);
        self::assertFalse($this->body()['active']);

        $this->postJson('/api/agents', ['name' => 'Cy', 'email' => 'cy@example.test', 'colour' => 'blue']);
        self::assertResponseStatusCodeSame(201);
        $body = $this->body();
        self::assertArrayNotHasKey('colour', $body);
        self::assertSame(self::AGENT_KEYS, array_keys($body));
    }

    /**
     * AC4 (URS-N-02): missing name and email are a JSON 422 keyed by field; nothing persisted.
     */
    public function testAc4UrsN02MissingFieldsReturn422WithBothErrorKeys(): void
    {
        $this->postJson('/api/agents', []);

        self::assertResponseStatusCodeSame(422);
        $this->assertJsonContentType();

        $body = $this->body();
        self::assertSame(['errors'], array_keys($body));
        self::assertIsArray($body['errors']);
        self::assertArrayHasKey('name', $body['errors']);
        self::assertArrayHasKey('email', $body['errors']);
        self::assertIsString($body['errors']['name']);
        self::assertNotSame('', $body['errors']['name']);
        self::assertIsString($body['errors']['email']);
        self::assertNotSame('', $body['errors']['email']);

        self::assertSame(0, $this->listAgents()['count']);
    }

    /**
     * AC5 (URS-N-02): blank, null, non-string and oversized name are a JSON 422 on errors.name only.
     */
    public function testAc5UrsN02InvalidNameReturns422(): void
    {
        $bodies = [
            ['name' => '', 'email' => 'x@example.test'],
            ['name' => '   ', 'email' => 'x@example.test'],
            ['name' => null, 'email' => 'x@example.test'],
            ['name' => 123, 'email' => 'x@example.test'],
            ['name' => str_repeat('x', 121), 'email' => 'x@example.test'],
        ];

        foreach ($bodies as $i => $payload) {
            $this->postJson('/api/agents', $payload);

            self::assertResponseStatusCodeSame(422, "case #$i");
            self::assertStringStartsWith('application/json', $this->contentType(), "case #$i");

            $body = $this->body();
            self::assertArrayHasKey('errors', $body, "case #$i");
            self::assertArrayHasKey('name', $body['errors'], "case #$i");
            self::assertIsString($body['errors']['name'], "case #$i");
            self::assertNotSame('', $body['errors']['name'], "case #$i");
            self::assertArrayNotHasKey('email', $body['errors'], "case #$i");
        }
    }

    /**
     * AC6 (URS-N-02): empty, malformed, null and oversized email are a JSON 422 on errors.email only.
     */
    public function testAc6UrsN02InvalidEmailReturns422(): void
    {
        $bodies = [
            ['name' => 'Ada', 'email' => ''],
            ['name' => 'Ada', 'email' => 'not-an-email'],
            ['name' => 'Ada', 'email' => 'ada@'],
            ['name' => 'Ada', 'email' => null],
            ['name' => 'Ada', 'email' => str_repeat('a', 170).'@example.test'],
        ];

        foreach ($bodies as $i => $payload) {
            $this->postJson('/api/agents', $payload);

            self::assertResponseStatusCodeSame(422, "case #$i");
            self::assertStringStartsWith('application/json', $this->contentType(), "case #$i");

            $body = $this->body();
            self::assertArrayHasKey('errors', $body, "case #$i");
            self::assertArrayHasKey('email', $body['errors'], "case #$i");
            self::assertIsString($body['errors']['email'], "case #$i");
            self::assertNotSame('', $body['errors']['email'], "case #$i");
            self::assertArrayNotHasKey('name', $body['errors'], "case #$i");
        }
    }

    /**
     * AC7 (URS-N-02): a non-boolean active is a JSON 422 on errors.active; nothing persisted.
     */
    public function testAc7UrsN02NonBooleanActiveReturns422(): void
    {
        $this->postJson('/api/agents', ['name' => 'Ada', 'email' => 'ada@example.test', 'active' => 'yes']);

        self::assertResponseStatusCodeSame(422);
        $this->assertJsonContentType();

        $body = $this->body();
        self::assertArrayHasKey('errors', $body);
        self::assertArrayHasKey('active', $body['errors']);
        self::assertIsString($body['errors']['active']);
        self::assertNotSame('', $body['errors']['active']);

        self::assertSame(0, $this->listAgents()['count']);
    }

    /**
     * AC8 (URS-F-01.2): duplicate email against a pre-existing agent is the prescribed JSON 422.
     */
    public function testAc8UrsF0102DuplicateEmailReturns422(): void
    {
        $this->createAgent('Ada', 'ada@example.test');

        $this->postJson('/api/agents', ['name' => 'Ada Twin', 'email' => 'ada@example.test']);

        self::assertResponseStatusCodeSame(422);
        $this->assertJsonContentType();
        self::assertSame(['errors' => ['email' => self::DUPLICATE_EMAIL_MESSAGE]], $this->body());

        $list = $this->listAgents();
        self::assertSame(1, $list['count']);
        self::assertSame('Ada', $list['items'][0]['name']);
    }

    /**
     * AC9 (URS-F-01.2): duplicate email both via the API; comparison is exact (case-sensitive).
     */
    public function testAc9UrsF0102DuplicateEmailViaApiIsCaseSensitive(): void
    {
        $this->postJson('/api/agents', ['name' => 'A', 'email' => 'dup@example.test']);
        self::assertResponseStatusCodeSame(201);

        $this->postJson('/api/agents', ['name' => 'A', 'email' => 'dup@example.test']);
        self::assertResponseStatusCodeSame(422);
        $this->assertJsonContentType();
        self::assertSame(['errors' => ['email' => self::DUPLICATE_EMAIL_MESSAGE]], $this->body());

        $this->postJson('/api/agents', ['name' => 'B', 'email' => 'Dup@example.test']);
        self::assertResponseStatusCodeSame(201);

        self::assertSame(2, $this->listAgents()['count']);
    }

    /**
     * AC10 (URS-N-02): malformed JSON and an empty body are a JSON 400 with an error string.
     */
    public function testAc10UrsN02MalformedJsonReturnsJson400(): void
    {
        foreach (['{not json', ''] as $i => $content) {
            $this->client->request('POST', '/api/agents', server: ['CONTENT_TYPE' => 'application/json'], content: $content);

            self::assertResponseStatusCodeSame(400, "case #$i");
            self::assertStringStartsWith('application/json', $this->contentType(), "case #$i");

            $body = $this->body();
            self::assertArrayHasKey('error', $body, "case #$i");
            self::assertIsString($body['error'], "case #$i");
            self::assertNotSame('', $body['error'], "case #$i");
        }
    }

    /**
     * AC11 (URS-N-02): an unsupported content type is a JSON 415 with an error string.
     */
    public function testAc11UrsN02UnsupportedContentTypeReturnsJson415(): void
    {
        $this->client->request('POST', '/api/agents', server: ['CONTENT_TYPE' => 'text/plain'], content: '{"name":"Ada","email":"ada@example.test"}');

        self::assertResponseStatusCodeSame(415);
        $this->assertJsonContentType();

        $body = $this->body();
        self::assertArrayHasKey('error', $body);
        self::assertIsString($body['error']);
        self::assertNotSame('', $body['error']);
    }

    /**
     * AC12 (URS-F-01.3): list orders active agents first, then by id within each group.
     */
    public function testAc12UrsF0103ListIsActiveFirstThenIdAscending(): void
    {
        $this->createAgent('Zed', 'zed@example.test', false);
        $this->createAgent('Ann', 'ann@example.test', true);
        $this->createAgent('Bea', 'bea@example.test', false);
        $this->createAgent('Cal', 'cal@example.test', true);

        $list = $this->listAgents();
        $this->assertJsonContentType();
        self::assertSame(4, $list['count']);
        self::assertSame(['Ann', 'Cal', 'Zed', 'Bea'], array_column($list['items'], 'name'));
        self::assertSame([true, true, false, false], array_column($list['items'], 'active'));
    }

    /**
     * AC13 (URS-F-01.3): empty list is exactly {"items": [], "count": 0}.
     */
    public function testAc13UrsF0103EmptyListReturnsEmptyEnvelope(): void
    {
        $list = $this->listAgents();
        $this->assertJsonContentType();

        self::assertSame(['items' => [], 'count' => 0], $list);
        self::assertSame(['items', 'count'], array_keys($list));
    }

    /**
     * AC14 (URS-F-01.3): each list item is exactly Agent::toArray().
     */
    public function testAc14UrsF0103ListItemHasAgentShape(): void
    {
        $agent = $this->createAgent();

        $list = $this->listAgents();
        self::assertSame(1, $list['count']);
        self::assertSame(self::AGENT_KEYS, array_keys($list['items'][0]));
        self::assertSame($agent->toArray(), $list['items'][0]);
    }

    /**
     * AC15 (URS-N-02): existing endpoints keep their status codes and JSON bodies.
     */
    public function testAc15UrsN02ExistingEndpointsUnchanged(): void
    {
        $this->postJson('/api/tickets', ['title' => 'Printer on fire', 'description' => 'Literally.', 'priority' => 'high']);
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
     * AC16: AgentRepository::findActiveFirst() orders active first, then by id (service level).
     */
    public function testAc16RepositoryFindActiveFirstOrdering(): void
    {
        $this->createAgent('Zed', 'zed@example.test', false);
        $this->createAgent('Ann', 'ann@example.test', true);
        $this->createAgent('Bea', 'bea@example.test', false);
        $this->createAgent('Cal', 'cal@example.test', true);

        /** @var AgentRepository $repository */
        $repository = static::getContainer()->get(AgentRepository::class);
        $agents = $repository->findActiveFirst();

        self::assertCount(4, $agents);
        self::assertContainsOnlyInstancesOf(Agent::class, $agents);
        self::assertSame(['Ann', 'Cal', 'Zed', 'Bea'], array_map(static fn (Agent $a): string => $a->getName(), $agents));
    }
}
