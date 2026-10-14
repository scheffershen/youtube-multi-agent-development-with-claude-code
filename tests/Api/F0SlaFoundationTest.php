<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Agent;
use App\Entity\Ticket;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Spec: specs/F0-sla-foundation.md (URS-002 SLA module, foundation item).
 *
 * One test method per acceptance criterion, written from the spec only.
 * AC1 (migration against the dev database) lives in tests/Unit/F0SlaFoundationMigrationTest.php
 * because it is not an HTTP-level test. AC3 is the existing suite itself (TicketApiTest).
 */
final class F0SlaFoundationTest extends ApiTestCase
{
    private const string DATE_ATOM_REGEX = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/';

    private const array TICKET_KEYS = ['id', 'title', 'description', 'priority', 'status', 'createdAt', 'dueAt', 'assignee'];

    private const array STATS_KEYS = ['total', 'open', 'in_progress', 'closed', 'closed_ratio'];

    /**
     * AC2: an Agent and a Ticket (explicit createdAt, explicit dueAt, assignee) survive a
     * persist + flush + clear + reload round trip with identical values.
     */
    public function testAc2EntitiesRoundTripThroughPersistence(): void
    {
        $createdAt = new \DateTimeImmutable('2026-03-01T10:00:00+00:00');
        $dueAt = new \DateTimeImmutable('2026-03-02T12:30:00+00:00');

        $agent = $this->createAgent('Grace', 'grace@example.test', false);
        $agentId = $agent->getId();
        self::assertNotNull($agentId);

        $ticket = $this->createTicket(
            title: 'Round trip',
            description: 'Persisted and reloaded',
            priority: 'high',
            createdAt: $createdAt,
            dueAt: $dueAt,
            assignee: $agent,
            status: 'in_progress',
        );
        $ticketId = $ticket->getId();
        self::assertNotNull($ticketId);

        $this->em()->clear();

        $reloadedAgent = $this->em()->find(Agent::class, $agentId);
        self::assertInstanceOf(Agent::class, $reloadedAgent);
        self::assertSame('Grace', $reloadedAgent->getName());
        self::assertSame('grace@example.test', $reloadedAgent->getEmail());
        self::assertFalse($reloadedAgent->isActive());

        $reloadedTicket = $this->em()->find(Ticket::class, $ticketId);
        self::assertInstanceOf(Ticket::class, $reloadedTicket);
        self::assertSame('Round trip', $reloadedTicket->getTitle());
        self::assertSame('Persisted and reloaded', $reloadedTicket->getDescription());
        self::assertSame('high', $reloadedTicket->getPriority());
        self::assertSame('in_progress', $reloadedTicket->getStatus());
        self::assertEquals($createdAt, $reloadedTicket->getCreatedAt());
        self::assertEquals($dueAt, $reloadedTicket->getDueAt());
        self::assertNotNull($reloadedTicket->getAssignee());
        self::assertSame($agentId, $reloadedTicket->getAssignee()->getId());
    }

    /**
     * AC4: GET /api/tickets after one POST keeps every existing key on items[0] and adds
     * dueAt (DATE_ATOM string) and assignee (present, null).
     */
    public function testAc4ListKeepsExistingKeysAndAddsDueAtAndAssignee(): void
    {
        $this->postJson('/api/tickets', ['title' => 'List me', 'description' => 'd', 'priority' => 'normal']);
        self::assertResponseStatusCodeSame(201);

        $this->client->request('GET', '/api/tickets');
        self::assertResponseStatusCodeSame(200);
        $list = $this->body();

        self::assertSame(1, $list['count']);
        $item = $list['items'][0];
        foreach (['id', 'title', 'description', 'priority', 'status', 'createdAt'] as $key) {
            self::assertArrayHasKey($key, $item);
        }

        self::assertIsString($item['dueAt']);
        self::assertInstanceOf(\DateTimeImmutable::class, \DateTimeImmutable::createFromFormat(\DATE_ATOM, $item['dueAt']));

        self::assertTrue(array_key_exists('assignee', $item));
        self::assertNull($item['assignee']);
    }

    /**
     * AC5: ApiTestCase exists, is abstract, extends WebTestCase, exposes the helpers as
     * protected, and TicketApiTest extends it.
     */
    public function testAc5ApiTestCaseShapeAndTicketApiTestExtendsIt(): void
    {
        self::assertFileExists(__DIR__.'/ApiTestCase.php');

        $base = new \ReflectionClass(ApiTestCase::class);
        self::assertTrue($base->isAbstract());
        self::assertTrue($base->isSubclassOf(WebTestCase::class));

        foreach (['postJson', 'patchJson', 'body', 'createAgent', 'createTicket'] as $helper) {
            self::assertTrue($base->hasMethod($helper), "ApiTestCase::$helper() is missing");
            self::assertTrue($base->getMethod($helper)->isProtected(), "ApiTestCase::$helper() must be protected");
        }

        self::assertTrue((new \ReflectionClass(TicketApiTest::class))->isSubclassOf(ApiTestCase::class));
    }

    /**
     * AC6 (URS-F-01.1): createAgent('Ada', 'ada@example.test') yields a persisted Agent,
     * active by default, with the exact toArray() shape.
     */
    public function testAc6UrsF0101CreateAgentDefaultsAndToArray(): void
    {
        $agent = $this->createAgent('Ada', 'ada@example.test');

        self::assertNotNull($agent->getId());
        self::assertTrue($agent->isActive());
        self::assertSame(
            ['id' => $agent->getId(), 'name' => 'Ada', 'email' => 'ada@example.test', 'active' => true],
            $agent->toArray(),
        );
    }

    /**
     * AC7 (URS-F-01.1): a second Agent with an existing email fails on flush with
     * UniqueConstraintViolationException.
     */
    public function testAc7UrsF0101DuplicateEmailThrowsUniqueConstraintViolation(): void
    {
        $this->createAgent('Ada', 'ada@example.test');

        $this->expectException(UniqueConstraintViolationException::class);
        $this->createAgent('Ada Twin', 'ada@example.test');
    }

    /**
     * AC8 (URS-F-02.1): assignee is null by default, survives assignment through reload,
     * and setAssignee(null) unassigns.
     */
    public function testAc8UrsF0201AssigneeNullAssignedAndUnassigned(): void
    {
        $ticket = $this->createTicket();
        $ticketId = $ticket->getId();
        self::assertNull($ticket->getAssignee());

        $agent = $this->createAgent();
        $agentId = $agent->getId();

        $ticket->setAssignee($agent);
        $this->em()->flush();
        $this->em()->clear();

        $reloaded = $this->em()->find(Ticket::class, $ticketId);
        self::assertNotNull($reloaded->getAssignee());
        self::assertSame($agentId, $reloaded->getAssignee()->getId());

        $reloaded->setAssignee(null);
        $this->em()->flush();
        $this->em()->clear();

        $reloadedAgain = $this->em()->find(Ticket::class, $ticketId);
        self::assertNull($reloadedAgain->getAssignee());
    }

    /**
     * AC9 (URS-F-03.1): POST with low / normal / high returns 201 and dueAt - createdAt of
     * exactly 72 h, 24 h and 8 h.
     */
    public function testAc9UrsF0301DueTimePerPriority(): void
    {
        $expected = ['low' => 259200, 'normal' => 86400, 'high' => 28800];

        foreach ($expected as $priority => $seconds) {
            $this->postJson('/api/tickets', ['title' => "P $priority", 'description' => 'd', 'priority' => $priority]);
            self::assertResponseStatusCodeSame(201);

            self::assertSame($seconds, $this->dueMinusCreatedSeconds($this->body()), "priority $priority");
        }
    }

    /**
     * AC10 (URS-F-03.1): constructor derives dueAt from createdAt + SLA target hours, and
     * Ticket::slaTargetHours() returns 72 / 24 / 8 for low / normal / high.
     */
    public function testAc10UrsF0301ConstructorDueAtAndSlaTargetHours(): void
    {
        $ticket = new Ticket('t', 'd', 'high', new \DateTimeImmutable('2026-01-01T00:00:00+00:00'));

        self::assertEquals(new \DateTimeImmutable('2026-01-01T08:00:00+00:00'), $ticket->getDueAt());

        self::assertSame(72, Ticket::slaTargetHours('low'));
        self::assertSame(24, Ticket::slaTargetHours('normal'));
        self::assertSame(8, Ticket::slaTargetHours('high'));
    }

    /**
     * AC11 (URS-F-03.2): unknown priorities fall back to the normal 24 h target, through
     * the API and through Ticket::slaTargetHours().
     */
    public function testAc11UrsF0302UnknownPriorityFallsBackToNormalTarget(): void
    {
        $this->postJson('/api/tickets', ['title' => 'Urgent one', 'description' => 'd', 'priority' => 'urgent']);
        self::assertResponseStatusCodeSame(201);
        self::assertSame(86400, $this->dueMinusCreatedSeconds($this->body()));

        self::assertSame(24, Ticket::slaTargetHours('urgent'));
        self::assertSame(24, Ticket::slaTargetHours(''));
        self::assertSame(24, Ticket::slaTargetHours('LOW'));
    }

    /**
     * AC12 (URS-F-03.3): an explicit dueAt is stored once and read back from the column,
     * never recomputed from priority; Ticket has no public setDueAt().
     */
    public function testAc12UrsF0303DueAtStoredOnceNeverRecomputed(): void
    {
        $t0 = new \DateTimeImmutable('2026-02-01T09:00:00+00:00');
        $explicitDueAt = $t0->modify('+1 hour');

        $ticket = $this->createTicket(priority: 'high', createdAt: $t0, dueAt: $explicitDueAt);
        $ticketId = $ticket->getId();

        $this->em()->flush();
        $this->em()->clear();

        $reloaded = $this->em()->find(Ticket::class, $ticketId);
        self::assertEquals($explicitDueAt, $reloaded->getDueAt());
        self::assertNotEquals($t0->modify('+8 hours'), $reloaded->getDueAt());

        $hasPublicSetDueAt = method_exists(Ticket::class, 'setDueAt')
            && (new \ReflectionMethod(Ticket::class, 'setDueAt'))->isPublic();
        self::assertFalse($hasPublicSetDueAt, 'Ticket must not expose a public setDueAt()');
    }

    /**
     * AC13 (URS-F-03.4): POST and GET /{id} both return dueAt as a DATE_ATOM string,
     * identical for the same ticket.
     */
    public function testAc13UrsF0304DueAtIsIso8601AndStableAcrossResponses(): void
    {
        $this->postJson('/api/tickets', ['title' => 'Iso', 'description' => 'd', 'priority' => 'low']);
        self::assertResponseStatusCodeSame(201);
        $created = $this->body();
        self::assertIsString($created['dueAt']);
        self::assertMatchesRegularExpression(self::DATE_ATOM_REGEX, $created['dueAt']);

        $this->client->request('GET', '/api/tickets/'.$created['id']);
        self::assertResponseStatusCodeSame(200);
        $fetched = $this->body();
        self::assertIsString($fetched['dueAt']);
        self::assertMatchesRegularExpression(self::DATE_ATOM_REGEX, $fetched['dueAt']);

        self::assertSame($created['dueAt'], $fetched['dueAt']);
    }

    /**
     * AC14 (URS-N-03): every ticket endpoint returns exactly the eight ticket keys, and
     * GET /api/stats keeps exactly its five keys.
     */
    public function testAc14UrsN03TicketKeySetIsExactOnEveryEndpoint(): void
    {
        $this->postJson('/api/tickets', ['title' => 'Keys', 'description' => 'd', 'priority' => 'normal']);
        self::assertResponseStatusCodeSame(201);
        $created = $this->body();
        self::assertSame(self::TICKET_KEYS, array_keys($created));
        $id = $created['id'];

        $this->client->request('GET', '/api/tickets');
        self::assertResponseStatusCodeSame(200);
        self::assertSame(self::TICKET_KEYS, array_keys($this->body()['items'][0]));

        $this->client->request('GET', "/api/tickets/$id");
        self::assertResponseStatusCodeSame(200);
        self::assertSame(self::TICKET_KEYS, array_keys($this->body()));

        $this->patchJson("/api/tickets/$id/status", ['status' => 'closed']);
        self::assertResponseStatusCodeSame(200);
        self::assertSame(self::TICKET_KEYS, array_keys($this->body()));

        $this->client->request('GET', '/api/stats');
        self::assertResponseStatusCodeSame(200);
        self::assertSame(self::STATS_KEYS, array_keys($this->body()));
    }

    /**
     * AC15 (URS-N-03): GET /api/tickets on an empty table still returns 200 with an empty
     * list and count 0.
     */
    public function testAc15UrsN03EmptyListStillReturnsItemsAndCount(): void
    {
        $this->client->request('GET', '/api/tickets');
        self::assertResponseStatusCodeSame(200);
        self::assertSame(['items' => [], 'count' => 0], $this->body());
    }

    /** @param array<string, mixed> $ticket */
    private function dueMinusCreatedSeconds(array $ticket): int
    {
        self::assertIsString($ticket['createdAt']);
        self::assertIsString($ticket['dueAt']);

        $createdAt = \DateTimeImmutable::createFromFormat(\DATE_ATOM, $ticket['createdAt']);
        $dueAt = \DateTimeImmutable::createFromFormat(\DATE_ATOM, $ticket['dueAt']);
        self::assertInstanceOf(\DateTimeImmutable::class, $createdAt);
        self::assertInstanceOf(\DateTimeImmutable::class, $dueAt);

        return $dueAt->getTimestamp() - $createdAt->getTimestamp();
    }
}
