<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Ticket;
use Monolog\Handler\TestHandler;
use Monolog\LogRecord;
use Psr\Log\LoggerInterface;

/**
 * U3: PATCH /api/tickets/{id}/assignee (assign, unassign) and the assignee object in all ticket JSON.
 * One test method per acceptance criterion of specs/U3-ticket-assignee.md.
 */
final class U3TicketAssigneeTest extends ApiTestCase
{
    private const TICKET_KEYS = ['id', 'title', 'description', 'priority', 'status', 'createdAt', 'dueAt', 'assignee'];

    private function contentType(): string
    {
        return (string) $this->client->getResponse()->headers->get('Content-Type');
    }

    private function assertJsonContentType(): void
    {
        self::assertStringStartsWith('application/json', $this->contentType());
    }

    private function attachLogHandler(): TestHandler
    {
        $this->client->disableReboot();
        $logger = static::getContainer()->get(LoggerInterface::class);
        $handler = new TestHandler();
        $logger->pushHandler($handler);

        return $handler;
    }

    private function reloadTicket(int $id): Ticket
    {
        $this->em()->clear();
        $ticket = $this->em()->find(Ticket::class, $id);
        self::assertInstanceOf(Ticket::class, $ticket);

        return $ticket;
    }

    public function testAc1UrsF0202AssignActiveAgentReturns200WithAssigneeObject(): void
    {
        $agent = $this->createAgent('Ada', 'ada@example.test');
        $ticket = $this->createTicket();

        $this->patchJson("/api/tickets/{$ticket->getId()}/assignee", ['agentId' => $agent->getId()]);

        self::assertResponseStatusCodeSame(200);
        $this->assertJsonContentType();
        $body = $this->body();
        self::assertSame(self::TICKET_KEYS, array_keys($body));
        self::assertSame(['id', 'name'], array_keys($body['assignee']));
        self::assertSame(['id' => $agent->getId(), 'name' => 'Ada'], $body['assignee']);

        $reloaded = $this->reloadTicket($ticket->getId());
        self::assertNotNull($reloaded->getAssignee());
        self::assertSame($agent->getId(), $reloaded->getAssignee()->getId());
    }

    public function testAc2UrsF0202ReassignReplacesAssigneeAndIsIdempotent(): void
    {
        $a = $this->createAgent('Ada', 'ada@example.test');
        $b = $this->createAgent('Bob', 'bob@example.test');
        $ticket = $this->createTicket(assignee: $a);
        $expected = ['id' => $b->getId(), 'name' => 'Bob'];

        $this->patchJson("/api/tickets/{$ticket->getId()}/assignee", ['agentId' => $b->getId()]);
        self::assertResponseStatusCodeSame(200);
        self::assertSame($expected, $this->body()['assignee']);

        $this->client->request('GET', "/api/tickets/{$ticket->getId()}");
        self::assertResponseStatusCodeSame(200);
        self::assertSame($expected, $this->body()['assignee']);

        $this->patchJson("/api/tickets/{$ticket->getId()}/assignee", ['agentId' => $b->getId()]);
        self::assertResponseStatusCodeSame(200);
        self::assertSame($expected, $this->body()['assignee']);
    }

    public function testAc3UrsF0202UnknownAgentReturnsJson404AndLeavesTicketUnchanged(): void
    {
        $a = $this->createAgent();
        $ticket = $this->createTicket(assignee: $a);

        $this->patchJson("/api/tickets/{$ticket->getId()}/assignee", ['agentId' => 9999]);
        self::assertResponseStatusCodeSame(404);
        $this->assertJsonContentType();
        self::assertSame(['error' => 'Agent not found'], $this->body());

        $this->client->request('GET', "/api/tickets/{$ticket->getId()}");
        self::assertResponseStatusCodeSame(200);
        self::assertSame($a->getId(), $this->body()['assignee']['id']);

        $this->patchJson("/api/tickets/{$ticket->getId()}/assignee", ['agentId' => 0]);
        self::assertResponseStatusCodeSame(404);
        self::assertSame(['error' => 'Agent not found'], $this->body());
    }

    public function testAc4UrsF0202InactiveAgentReturns422AndLeavesTicketUnchanged(): void
    {
        $a = $this->createAgent('Ada', 'ada@example.test');
        $inactive = $this->createAgent('Zed', 'zed@example.test', false);
        $ticket = $this->createTicket(assignee: $a);

        $this->patchJson("/api/tickets/{$ticket->getId()}/assignee", ['agentId' => $inactive->getId()]);
        self::assertResponseStatusCodeSame(422);
        $this->assertJsonContentType();
        self::assertSame(['errors' => ['agentId' => 'Agent is not active.']], $this->body());

        $this->client->request('GET', "/api/tickets/{$ticket->getId()}");
        self::assertResponseStatusCodeSame(200);
        self::assertSame(['id' => $a->getId(), 'name' => 'Ada'], $this->body()['assignee']);

        $reloaded = $this->reloadTicket($ticket->getId());
        self::assertNotNull($reloaded->getAssignee());
        self::assertSame($a->getId(), $reloaded->getAssignee()->getId());
    }

    public function testAc5UrsF0202InactiveAgentOnUnassignedTicketReturns422(): void
    {
        $inactive = $this->createAgent('Zed', 'zed@example.test', false);
        $ticket = $this->createTicket();

        $this->patchJson("/api/tickets/{$ticket->getId()}/assignee", ['agentId' => $inactive->getId()]);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(['errors' => ['agentId' => 'Agent is not active.']], $this->body());

        $this->client->request('GET', "/api/tickets/{$ticket->getId()}");
        self::assertResponseStatusCodeSame(200);
        $body = $this->body();
        self::assertArrayHasKey('assignee', $body);
        self::assertNull($body['assignee']);
    }

    public function testAc6UrsN02UnknownTicketReturnsJson404(): void
    {
        $agent = $this->createAgent();

        $this->patchJson('/api/tickets/9999/assignee', ['agentId' => $agent->getId()]);
        self::assertResponseStatusCodeSame(404);
        $this->assertJsonContentType();
        self::assertSame(['error' => 'Ticket not found'], $this->body());

        $this->patchJson('/api/tickets/9999/assignee', ['agentId' => null]);
        self::assertResponseStatusCodeSame(404);
        $this->assertJsonContentType();
        self::assertSame(['error' => 'Ticket not found'], $this->body());
    }

    public function testAc7UrsN02NonNumericTicketIdReturnsJson404(): void
    {
        $this->patchJson('/api/tickets/abc/assignee', ['agentId' => null]);

        self::assertResponseStatusCodeSame(404);
        $this->assertJsonContentType();
        $body = $this->body();
        self::assertArrayHasKey('error', $body);
        self::assertIsString($body['error']);
        self::assertNotSame('', $body['error']);
    }

    public function testAc8UrsF0203UnassignReturns200WithNullAssignee(): void
    {
        $agent = $this->createAgent();
        $ticket = $this->createTicket(assignee: $agent);

        $this->patchJson("/api/tickets/{$ticket->getId()}/assignee", ['agentId' => null]);
        self::assertResponseStatusCodeSame(200);
        $body = $this->body();
        self::assertSame(self::TICKET_KEYS, array_keys($body));
        self::assertTrue(array_key_exists('assignee', $body));
        self::assertNull($body['assignee']);

        $this->client->request('GET', "/api/tickets/{$ticket->getId()}");
        self::assertResponseStatusCodeSame(200);
        $shown = $this->body();
        self::assertArrayHasKey('assignee', $shown);
        self::assertNull($shown['assignee']);

        self::assertNull($this->reloadTicket($ticket->getId())->getAssignee());
    }

    public function testAc9UrsF0203UnassignIsIdempotent(): void
    {
        $ticket = $this->createTicket();

        $this->patchJson("/api/tickets/{$ticket->getId()}/assignee", ['agentId' => null]);

        self::assertResponseStatusCodeSame(200);
        $body = $this->body();
        self::assertArrayHasKey('assignee', $body);
        self::assertNull($body['assignee']);
    }

    public function testAc10UrsN02MissingAgentIdReturns422NotUnassignment(): void
    {
        $agent = $this->createAgent();
        $ticket = $this->createTicket(assignee: $agent);
        $uri = "/api/tickets/{$ticket->getId()}/assignee";

        $this->client->request('PATCH', $uri, server: ['CONTENT_TYPE' => 'application/json'], content: '{}');
        self::assertResponseStatusCodeSame(422);
        $this->assertJsonContentType();
        $this->assertAgentIdValidationError($this->body());

        $this->patchJson($uri, ['other' => 1]);
        self::assertResponseStatusCodeSame(422);
        $this->assertJsonContentType();
        $this->assertAgentIdValidationError($this->body());

        $this->client->request('GET', "/api/tickets/{$ticket->getId()}");
        self::assertResponseStatusCodeSame(200);
        self::assertSame($agent->getId(), $this->body()['assignee']['id']);
    }

    public function testAc11UrsN02MistypedAgentIdReturns422(): void
    {
        $ticket = $this->createTicket();
        $uri = "/api/tickets/{$ticket->getId()}/assignee";

        foreach (['abc', '5', true, 1.5] as $value) {
            $this->patchJson($uri, ['agentId' => $value]);
            $label = var_export($value, true);
            self::assertResponseStatusCodeSame(422, "agentId = $label");
            self::assertStringStartsWith('application/json', $this->contentType(), "agentId = $label");
            $this->assertAgentIdValidationError($this->body(), "agentId = $label");
        }
    }

    public function testAc12UrsN02MalformedJsonReturnsJson400(): void
    {
        $ticket = $this->createTicket();
        $uri = "/api/tickets/{$ticket->getId()}/assignee";

        foreach (['{not json', ''] as $content) {
            $this->client->request('PATCH', $uri, server: ['CONTENT_TYPE' => 'application/json'], content: $content);
            $label = var_export($content, true);
            self::assertResponseStatusCodeSame(400, "content = $label");
            self::assertStringStartsWith('application/json', $this->contentType(), "content = $label");
            $body = $this->body();
            self::assertArrayHasKey('error', $body, "content = $label");
            self::assertIsString($body['error'], "content = $label");
            self::assertNotSame('', $body['error'], "content = $label");
        }
    }

    public function testAc13UrsN02UnsupportedContentTypeReturnsJson415(): void
    {
        $ticket = $this->createTicket();

        $this->client->request('PATCH', "/api/tickets/{$ticket->getId()}/assignee", server: ['CONTENT_TYPE' => 'text/plain'], content: '{"agentId":null}');

        self::assertResponseStatusCodeSame(415);
        $this->assertJsonContentType();
        $body = $this->body();
        self::assertArrayHasKey('error', $body);
        self::assertIsString($body['error']);
        self::assertNotSame('', $body['error']);
    }

    public function testAc14UrsF0204ListShowsAssigneeObject(): void
    {
        $agent = $this->createAgent('Ada', 'ada@example.test');
        $this->createTicket(title: 'Assigned', assignee: $agent, createdAt: new \DateTimeImmutable('-2 hours'));
        $this->createTicket(title: 'Free', createdAt: new \DateTimeImmutable('-1 hour'));

        $this->client->request('GET', '/api/tickets');

        self::assertResponseStatusCodeSame(200);
        $list = $this->body();
        self::assertSame(2, $list['count']);
        self::assertSame('Free', $list['items'][0]['title']);
        self::assertNull($list['items'][0]['assignee']);
        self::assertSame('Assigned', $list['items'][1]['title']);
        self::assertSame(['id' => $agent->getId(), 'name' => 'Ada'], $list['items'][1]['assignee']);
        foreach ($list['items'] as $item) {
            self::assertSame(self::TICKET_KEYS, array_keys($item));
        }
    }

    public function testAc15UrsF0204ShowAndStatusCarryAssigneeObject(): void
    {
        $agent = $this->createAgent('Ada', 'ada@example.test');
        $ticket = $this->createTicket(assignee: $agent);
        $expected = ['id' => $agent->getId(), 'name' => 'Ada'];

        $this->client->request('GET', "/api/tickets/{$ticket->getId()}");
        self::assertResponseStatusCodeSame(200);
        self::assertSame($expected, $this->body()['assignee']);

        $this->patchJson("/api/tickets/{$ticket->getId()}/status", ['status' => 'closed']);
        self::assertResponseStatusCodeSame(200);
        $body = $this->body();
        self::assertSame('closed', $body['status']);
        self::assertSame($expected, $body['assignee']);
    }

    public function testAc16UrsF0204CreateStillYieldsNullAssignee(): void
    {
        $this->postJson('/api/tickets', ['title' => 'New', 'description' => 'x', 'priority' => 'low']);

        self::assertResponseStatusCodeSame(201);
        $body = $this->body();
        self::assertSame(self::TICKET_KEYS, array_keys($body));
        self::assertNull($body['assignee']);
    }

    public function testAc17UrsF0204InactiveAgentStaysVisibleAsAssignee(): void
    {
        $agent = $this->createAgent('Ada', 'ada@example.test');
        $ticket = $this->createTicket(assignee: $agent);
        $agent->setActive(false);
        $this->em()->flush();

        $this->client->request('GET', "/api/tickets/{$ticket->getId()}");

        self::assertResponseStatusCodeSame(200);
        self::assertSame(['id' => $agent->getId(), 'name' => 'Ada'], $this->body()['assignee']);
    }

    public function testAc18UrsN01AssignmentIsLoggedAtInfoWithTicketIdAndNewValue(): void
    {
        $agent = $this->createAgent();
        $ticket = $this->createTicket();
        $handler = $this->attachLogHandler();

        $this->patchJson("/api/tickets/{$ticket->getId()}/assignee", ['agentId' => $agent->getId()]);

        self::assertResponseStatusCodeSame(200);
        self::assertTrue($handler->hasInfoThatPasses(
            fn (LogRecord $r) => 'Ticket assignee changed' === $r->message
                && ['id' => $ticket->getId(), 'assignee' => $agent->getId()] === $r->context
        ));
    }

    public function testAc19UrsN01UnassignmentIsLogged(): void
    {
        $agent = $this->createAgent();
        $ticket = $this->createTicket(assignee: $agent);
        $handler = $this->attachLogHandler();

        $this->patchJson("/api/tickets/{$ticket->getId()}/assignee", ['agentId' => null]);

        self::assertResponseStatusCodeSame(200);
        self::assertTrue($handler->hasInfoThatPasses(
            fn (LogRecord $r) => 'Ticket assignee changed' === $r->message
                && ['id' => $ticket->getId(), 'assignee' => null] === $r->context
        ));
    }

    public function testAc20UrsN01RejectedRequestsAreNotLogged(): void
    {
        $inactive = $this->createAgent('Zed', 'zed@example.test', false);
        $ticket = $this->createTicket();
        $handler = $this->attachLogHandler();
        $uri = "/api/tickets/{$ticket->getId()}/assignee";

        $this->patchJson($uri, ['agentId' => $inactive->getId()]);
        self::assertResponseStatusCodeSame(422);

        $this->patchJson($uri, ['agentId' => 9999]);
        self::assertResponseStatusCodeSame(404);

        self::assertFalse($handler->hasInfoThatContains('Ticket assignee changed'));
    }

    public function testAc21UrsN03ExistingEndpointsKeepTheirResponses(): void
    {
        $ticket = $this->createTicket();
        $invalidStatusBody = ['error' => 'Invalid status', 'allowed' => ['open', 'in_progress', 'closed']];

        $this->patchJson("/api/tickets/{$ticket->getId()}/status", ['status' => 'done']);
        self::assertResponseStatusCodeSame(422);
        self::assertSame($invalidStatusBody, $this->body());

        $this->client->request('GET', '/api/tickets?status=bogus');
        self::assertResponseStatusCodeSame(400);
        self::assertSame($invalidStatusBody, $this->body());

        $this->patchJson('/api/tickets/9999/status', ['status' => 'closed']);
        self::assertResponseStatusCodeSame(404);
        self::assertSame(['error' => 'Ticket not found'], $this->body());

        $this->client->request('GET', '/api/stats');
        self::assertResponseStatusCodeSame(200);
        self::assertSame(['total', 'open', 'in_progress', 'closed', 'closed_ratio'], array_keys($this->body()));
    }

    /** @param array<string, mixed> $body */
    private function assertAgentIdValidationError(array $body, string $message = ''): void
    {
        self::assertSame(['errors'], array_keys($body), $message);
        self::assertIsArray($body['errors'], $message);
        self::assertArrayHasKey('agentId', $body['errors'], $message);
        self::assertIsString($body['errors']['agentId'], $message);
        self::assertNotSame('', $body['errors']['agentId'], $message);
    }
}
