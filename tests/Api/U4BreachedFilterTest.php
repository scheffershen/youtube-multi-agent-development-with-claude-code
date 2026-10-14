<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Ticket;
use App\Repository\TicketRepository;
use Doctrine\ORM\QueryBuilder;

/**
 * U4: GET /api/tickets?breached=true|false filter, combinable with the status filter.
 * One test method per acceptance criterion of specs/U4-breached-filter.md.
 * AC11 is a service-level test on TicketRepository (fetched from the container) and lives
 * here because the schema is already reset in ApiTestCase::setUp().
 */
final class U4BreachedFilterTest extends ApiTestCase
{
    private const array BREACHED_ALLOWED = ['true', 'false'];
    private const array STATUS_ALLOWED = ['open', 'in_progress', 'closed'];
    private const array TICKET_KEYS = ['id', 'title', 'description', 'priority', 'status', 'createdAt', 'dueAt', 'assignee'];

    private function at(string $modifier): \DateTimeImmutable
    {
        return new \DateTimeImmutable($modifier);
    }

    /** The constructor always fills dueAt, so a legacy NULL row is produced by a raw UPDATE. */
    private function nullDueAt(Ticket $ticket): void
    {
        $this->em()->getConnection()->executeStatement('UPDATE ticket SET due_at = NULL WHERE id = ?', [$ticket->getId()]);
        $this->em()->clear();
    }

    /** The five-ticket fixture shared by AC2 and AC3. */
    private function createBreachMixFixture(): void
    {
        $this->createTicket(title: 'Old breached open', status: 'open', createdAt: $this->at('-5 hours'), dueAt: $this->at('-1 hour'));
        $this->createTicket(title: 'Closed past due', status: 'closed', createdAt: $this->at('-4 hours'), dueAt: $this->at('-3 hours'));
        $this->createTicket(title: 'Open not due', status: 'open', createdAt: $this->at('-3 hours'), dueAt: $this->at('+1 hour'));
        $this->createTicket(title: 'Breached in progress', status: 'in_progress', createdAt: $this->at('-2 hours'), dueAt: $this->at('-30 minutes'));
        $legacy = $this->createTicket(title: 'Legacy null due', status: 'open', createdAt: $this->at('-1 hour'));
        $this->nullDueAt($legacy);
    }

    /** @param list<Ticket> $tickets @return list<string> */
    private static function titles(array $tickets): array
    {
        return array_map(static fn (Ticket $t) => $t->toArray()['title'], $tickets);
    }

    private function assertJsonContentType(string $message = ''): void
    {
        self::assertStringStartsWith('application/json', (string) $this->client->getResponse()->headers->get('Content-Type'), $message);
    }

    public function testAc1UrsN03WithoutParameterBehaviourIsUnchangedNewestFirst(): void
    {
        $this->createTicket(title: 'Breached open', status: 'open', createdAt: $this->at('-3 hours'), dueAt: $this->at('-1 hour'));
        $this->createTicket(title: 'Closed', status: 'closed', createdAt: $this->at('-2 hours'), dueAt: $this->at('-1 hour'));
        $this->createTicket(title: 'Open not due', status: 'open', createdAt: $this->at('-1 hour'), dueAt: $this->at('+1 hour'));

        $this->client->request('GET', '/api/tickets');
        self::assertResponseStatusCodeSame(200);
        $body = $this->body();
        self::assertSame(3, $body['count']);
        self::assertCount(3, $body['items']);

        $createdAt = array_map(static fn (array $item) => (new \DateTimeImmutable($item['createdAt']))->getTimestamp(), $body['items']);
        for ($i = 1, $n = \count($createdAt); $i < $n; ++$i) {
            self::assertLessThan($createdAt[$i - 1], $createdAt[$i], sprintf('items[%d].createdAt must be strictly older than items[%d].createdAt', $i, $i - 1));
        }
    }

    public function testAc2UrsF0401BreachedTrueReturnsOnlyBreachedTicketsNewestFirst(): void
    {
        $this->createBreachMixFixture();

        $this->client->request('GET', '/api/tickets?breached=true');
        self::assertResponseStatusCodeSame(200);
        $body = $this->body();
        self::assertSame(2, $body['count']);
        self::assertSame(['Breached in progress', 'Old breached open'], array_column($body['items'], 'title'));
        foreach ($body['items'] as $item) {
            self::assertNotSame('closed', $item['status'], $item['title']);
            self::assertNotNull($item['dueAt'], $item['title']);
        }
    }

    public function testAc3UrsF0402BreachedFalseReturnsExactlyTheComplement(): void
    {
        $this->createBreachMixFixture();

        $this->client->request('GET', '/api/tickets?breached=false');
        self::assertResponseStatusCodeSame(200);
        $notBreached = $this->body();
        self::assertSame(3, $notBreached['count']);
        self::assertSame(['Legacy null due', 'Open not due', 'Closed past due'], array_column($notBreached['items'], 'title'));

        $this->client->request('GET', '/api/tickets?breached=true');
        self::assertResponseStatusCodeSame(200);
        $breached = $this->body();

        $this->client->request('GET', '/api/tickets');
        self::assertResponseStatusCodeSame(200);
        $all = $this->body();
        self::assertSame(5, $all['count']);

        $breachedIds = array_column($breached['items'], 'id');
        $notBreachedIds = array_column($notBreached['items'], 'id');
        $allIds = array_column($all['items'], 'id');

        $union = array_merge($breachedIds, $notBreachedIds);
        sort($union);
        sort($allIds);
        self::assertSame($allIds, $union, 'true set and false set must partition the full list');
        self::assertSame([], array_values(array_intersect($breachedIds, $notBreachedIds)), 'true set and false set must be disjoint');
    }

    public function testAc4UrsF0401BreachedTrueWithNoMatchReturnsEmptyList(): void
    {
        $this->createTicket(title: 'Open not due', status: 'open', dueAt: $this->at('+1 hour'));
        $this->createTicket(title: 'Closed past due', status: 'closed', dueAt: $this->at('-1 hour'));

        $this->client->request('GET', '/api/tickets?breached=true');
        self::assertResponseStatusCodeSame(200);
        self::assertSame(['items' => [], 'count' => 0], $this->body());
    }

    public function testAc5UrsF0402UnknownValueReturns400NamingAllowedValues(): void
    {
        $this->createTicket(status: 'open');

        $this->client->request('GET', '/api/tickets?breached=yes');
        self::assertResponseStatusCodeSame(400);
        $this->assertJsonContentType();
        self::assertSame(['error' => 'Invalid breached', 'allowed' => self::BREACHED_ALLOWED], $this->body());
    }

    public function testAc6UrsN02EdgeValuesAreRejectedWith400Never500(): void
    {
        $this->createTicket(status: 'open');

        foreach (['', '1', '0', 'True', 'TRUE', '%20true', 'treu'] as $value) {
            $this->client->request('GET', '/api/tickets?breached='.$value);
            self::assertResponseStatusCodeSame(400, "breached=$value");
            $body = $this->body();
            self::assertSame('Invalid breached', $body['error'], "breached=$value");
            self::assertSame(self::BREACHED_ALLOWED, $body['allowed'], "breached=$value");
        }
    }

    public function testAc7UrsN02NonScalarValueIsJson400NotHtml(): void
    {
        $this->createTicket(status: 'open');

        foreach (['/api/tickets?breached[]=true', '/api/tickets?breached[a]=true'] as $uri) {
            $this->client->request('GET', $uri);
            self::assertResponseStatusCodeSame(400, $uri);
            $this->assertJsonContentType($uri);
            self::assertSame(['error' => 'Invalid breached', 'allowed' => self::BREACHED_ALLOWED], $this->body(), $uri);
        }
    }

    public function testAc8UrsF0403BreachFilterCombinesWithStatusFilter(): void
    {
        $this->createTicket(title: 'Open breached', status: 'open', createdAt: $this->at('-4 hours'), dueAt: $this->at('-2 hours'));
        $this->createTicket(title: 'Open fine', status: 'open', createdAt: $this->at('-3 hours'), dueAt: $this->at('+2 hours'));
        $this->createTicket(title: 'In progress breached', status: 'in_progress', createdAt: $this->at('-2 hours'), dueAt: $this->at('-1 hour'));
        $this->createTicket(title: 'Closed past due', status: 'closed', createdAt: $this->at('-1 hour'), dueAt: $this->at('-1 hour'));

        $this->client->request('GET', '/api/tickets?status=open&breached=true');
        self::assertResponseStatusCodeSame(200);
        $body = $this->body();
        self::assertSame(1, $body['count']);
        self::assertSame('Open breached', $body['items'][0]['title']);

        $this->client->request('GET', '/api/tickets?breached=true&status=in_progress');
        self::assertResponseStatusCodeSame(200);
        $body = $this->body();
        self::assertSame(1, $body['count']);
        self::assertSame('In progress breached', $body['items'][0]['title']);

        $this->client->request('GET', '/api/tickets?status=closed&breached=true');
        self::assertResponseStatusCodeSame(200);
        self::assertSame(['items' => [], 'count' => 0], $this->body());

        $this->client->request('GET', '/api/tickets?status=closed&breached=false');
        self::assertResponseStatusCodeSame(200);
        $body = $this->body();
        self::assertSame(1, $body['count']);
        self::assertSame('Closed past due', $body['items'][0]['title']);

        $this->client->request('GET', '/api/tickets?status=open&breached=false');
        self::assertResponseStatusCodeSame(200);
        $body = $this->body();
        self::assertSame(1, $body['count']);
        self::assertSame('Open fine', $body['items'][0]['title']);
    }

    public function testAc9UrsN02CombinedInvalidValuesStatusErrorTakesPrecedence(): void
    {
        $this->createTicket(status: 'open');

        $statusBody = ['error' => 'Invalid status', 'allowed' => self::STATUS_ALLOWED];
        $breachedBody = ['error' => 'Invalid breached', 'allowed' => self::BREACHED_ALLOWED];

        $this->client->request('GET', '/api/tickets?status=done&breached=true');
        self::assertResponseStatusCodeSame(400);
        $this->assertJsonContentType('status=done&breached=true');
        self::assertSame($statusBody, $this->body());

        $this->client->request('GET', '/api/tickets?status=open&breached=maybe');
        self::assertResponseStatusCodeSame(400);
        $this->assertJsonContentType('status=open&breached=maybe');
        self::assertSame($breachedBody, $this->body());

        $this->client->request('GET', '/api/tickets?status=done&breached=maybe');
        self::assertResponseStatusCodeSame(400);
        $this->assertJsonContentType('status=done&breached=maybe');
        self::assertSame($statusBody, $this->body());
    }

    public function testAc10UrsN03JsonShapeUnchangedAndOtherEndpointsUntouched(): void
    {
        $this->createTicket(title: 'Breached open', status: 'open', dueAt: $this->at('-1 hour'));

        $responses = [];
        foreach (['/api/tickets', '/api/tickets?breached=true', '/api/tickets?status=open&breached=true'] as $uri) {
            $this->client->request('GET', $uri);
            self::assertResponseStatusCodeSame(200, $uri);
            $body = $this->body();
            self::assertSame(['items', 'count'], array_keys($body), $uri);
            self::assertSame(self::TICKET_KEYS, array_keys($body['items'][0]), $uri);
            $responses[$uri] = $body;
        }
        self::assertSame($responses['/api/tickets']['items'][0], $responses['/api/tickets?breached=true']['items'][0]);
        self::assertSame($responses['/api/tickets']['items'][0], $responses['/api/tickets?status=open&breached=true']['items'][0]);

        $this->postJson('/api/tickets', ['title' => 'Untouched', 'description' => 'd', 'priority' => 'normal']);
        self::assertResponseStatusCodeSame(201);
        $id = $this->body()['id'];

        $this->client->request('GET', "/api/tickets/$id");
        self::assertResponseStatusCodeSame(200);

        $this->patchJson("/api/tickets/$id/status", ['status' => 'done']);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('Invalid status', $this->body()['error']);

        $this->client->request('GET', '/api/stats');
        self::assertResponseStatusCodeSame(200);
        self::assertSame(['total', 'open', 'in_progress', 'closed', 'closed_ratio'], array_keys($this->body()));
    }

    public function testAc11RepositoryExposesOneReusableClockPinnedBreachPredicate(): void
    {
        $this->createTicket(title: 'Future due', status: 'open', createdAt: $this->at('-3 hours'), dueAt: $this->at('+1 hour'));
        $pastDue = $this->createTicket(title: 'Past due', status: 'open', createdAt: $this->at('-2 hours'), dueAt: $this->at('-1 hour'));

        /** @var TicketRepository $repository */
        $repository = static::getContainer()->get(TicketRepository::class);

        // (a) withBreached() returns a QueryBuilder whose DQL mentions t.dueAt.
        $qb = $repository->withBreached($repository->createNewestFirstQueryBuilder(), true, new \DateTimeImmutable());
        self::assertInstanceOf(QueryBuilder::class, $qb);
        self::assertStringContainsString('t.dueAt', $qb->getDQL());

        // (b) findNewestFirst() with the breached flag and the real clock.
        self::assertSame(['Past due'], self::titles($repository->findNewestFirst(null, true, new \DateTimeImmutable())));
        self::assertSame(['Future due'], self::titles($repository->findNewestFirst(null, false, new \DateTimeImmutable())));

        // (c) pinning the clock changes the verdict without touching the rows.
        // With now = T+2h both tickets are past due; newest createdAt first is 'Past due' (T-2h) then 'Future due' (T-3h).
        $pinnedLater = $repository->findNewestFirst(null, true, $this->at('+2 hours'));
        self::assertCount(2, $pinnedLater);
        self::assertSame(['Past due', 'Future due'], self::titles($pinnedLater));
        self::assertSame([], $repository->findNewestFirst(null, true, $this->at('-2 hours')));

        // (d) the boundary is strict: dueAt === now is not breached.
        $boundary = $pastDue->getDueAt();
        self::assertNotNull($boundary);
        self::assertSame([], $repository->findNewestFirst(null, true, $boundary));
        self::assertCount(2, $repository->findNewestFirst(null, false, $boundary));

        // (e) stacking works in both orders.
        $now = new \DateTimeImmutable();
        $qb1 = $repository->createNewestFirstQueryBuilder();
        $statusThenBreached = $repository->withBreached($repository->withStatus($qb1, 'open'), true, $now)->getQuery()->getResult();
        $qb2 = $repository->createNewestFirstQueryBuilder();
        $breachedThenStatus = $repository->withStatus($repository->withBreached($qb2, true, $now), 'open')->getQuery()->getResult();
        self::assertSame(['Past due'], self::titles($statusThenBreached));
        self::assertSame(['Past due'], self::titles($breachedThenStatus));
        self::assertSame($statusThenBreached[0]->getId(), $breachedThenStatus[0]->getId());

        // (f) backward compatibility of the existing signatures.
        $legacy = array_map(static fn (Ticket $t) => $t->toArray(), $repository->findAllNewestFirst());
        self::assertCount(2, $legacy);
        self::assertSame(['Past due', 'Future due'], array_column($legacy, 'title'));
        self::assertSame($legacy, array_map(static fn (Ticket $t) => $t->toArray(), $repository->findNewestFirst()));
        self::assertSame($legacy, array_map(static fn (Ticket $t) => $t->toArray(), $repository->findNewestFirst('open')));
    }
}
