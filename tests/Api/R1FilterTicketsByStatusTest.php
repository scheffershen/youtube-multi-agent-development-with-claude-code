<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Ticket;
use App\Repository\TicketRepository;
use Doctrine\ORM\QueryBuilder;

/**
 * R1: Filter GET /api/tickets by status query parameter.
 * One test method per acceptance criterion of specs/R1-filter-tickets-by-status.md.
 * AC10 is a service-level test on TicketRepository (fetched from the container) and lives
 * here because the schema is already reset in ApiTestCase::setUp().
 */
final class R1FilterTicketsByStatusTest extends ApiTestCase
{
    private const array ALLOWED = ['open', 'in_progress', 'closed'];
    private const array TICKET_KEYS = ['id', 'title', 'description', 'priority', 'status', 'createdAt', 'dueAt', 'assignee'];

    private function at(string $modifier): \DateTimeImmutable
    {
        return new \DateTimeImmutable($modifier);
    }

    public function testAc1WithoutParameterReturnsAllTicketsNewestFirst(): void
    {
        $this->createTicket(title: 'Open', status: 'open', createdAt: $this->at('-3 hours'));
        $this->createTicket(title: 'In progress', status: 'in_progress', createdAt: $this->at('-2 hours'));
        $this->createTicket(title: 'Closed', status: 'closed', createdAt: $this->at('-1 hour'));

        $this->client->request('GET', '/api/tickets');
        self::assertResponseStatusCodeSame(200);
        $body = $this->body();
        self::assertSame(3, $body['count']);
        self::assertSame(['closed', 'in_progress', 'open'], array_column($body['items'], 'status'));
    }

    public function testAc2StatusOpenReturnsOnlyOpenTicketsNewestFirst(): void
    {
        $this->createTicket(title: 'Oldest open', status: 'open', createdAt: $this->at('-3 hours'));
        $this->createTicket(title: 'Closed', status: 'closed', createdAt: $this->at('-2 hours'));
        $this->createTicket(title: 'Newest open', status: 'open', createdAt: $this->at('-1 hour'));
        $this->createTicket(title: 'In progress', status: 'in_progress', createdAt: $this->at('-30 minutes'));

        $this->client->request('GET', '/api/tickets?status=open');
        self::assertResponseStatusCodeSame(200);
        $body = $this->body();
        self::assertSame(2, $body['count']);
        self::assertSame('Newest open', $body['items'][0]['title']);
        foreach ($body['items'] as $item) {
            self::assertSame('open', $item['status']);
        }
    }

    public function testAc3EveryKnownStatusFiltersCorrectly(): void
    {
        foreach (Ticket::STATUSES as $status) {
            $this->createTicket(title: "Ticket $status", status: $status);
        }

        foreach (Ticket::STATUSES as $status) {
            $this->client->request('GET', '/api/tickets?status='.$status);
            self::assertResponseStatusCodeSame(200, "status $status");
            $body = $this->body();
            self::assertSame(1, $body['count'], "status $status");
            self::assertSame($status, $body['items'][0]['status'], "status $status");
        }
    }

    public function testAc4ValidStatusWithNoMatchReturnsEmptyList(): void
    {
        $this->createTicket(status: 'open');

        $this->client->request('GET', '/api/tickets?status=closed');
        self::assertResponseStatusCodeSame(200);
        self::assertSame(['items' => [], 'count' => 0], $this->body());
    }

    public function testAc5UnknownStatusReturns400NamingAllowedValues(): void
    {
        $this->createTicket(status: 'open');

        $this->client->request('GET', '/api/tickets?status=done');
        self::assertResponseStatusCodeSame(400);
        self::assertStringStartsWith('application/json', (string) $this->client->getResponse()->headers->get('Content-Type'));
        self::assertSame(['error' => 'Invalid status', 'allowed' => self::ALLOWED], $this->body());
    }

    public function testAc6UrsN02EdgeValuesAreRejectedWith400Never500(): void
    {
        $this->createTicket(status: 'open');

        foreach (['', 'Open', 'OPEN', '%20open', 'opne'] as $value) {
            $this->client->request('GET', '/api/tickets?status='.$value);
            self::assertResponseStatusCodeSame(400, "status=$value");
            $body = $this->body();
            self::assertSame('Invalid status', $body['error'], "status=$value");
            self::assertSame(Ticket::STATUSES, $body['allowed'], "status=$value");
        }
    }

    public function testAc7UrsN02NonScalarValueIsJson400NotHtml(): void
    {
        $this->createTicket(status: 'open');

        foreach (['/api/tickets?status[]=open', '/api/tickets?status[a]=open'] as $uri) {
            $this->client->request('GET', $uri);
            self::assertResponseStatusCodeSame(400, $uri);
            self::assertStringStartsWith('application/json', (string) $this->client->getResponse()->headers->get('Content-Type'), $uri);
            self::assertSame(['error' => 'Invalid status', 'allowed' => self::ALLOWED], $this->body(), $uri);
        }
    }

    public function testAc8UrsN03JsonShapeUnchangedWithAndWithoutFilter(): void
    {
        $this->createTicket(status: 'open');

        $this->client->request('GET', '/api/tickets');
        self::assertResponseStatusCodeSame(200);
        $unfiltered = $this->body();
        self::assertSame(['items', 'count'], array_keys($unfiltered));
        self::assertSame(self::TICKET_KEYS, array_keys($unfiltered['items'][0]));

        $this->client->request('GET', '/api/tickets?status=open');
        self::assertResponseStatusCodeSame(200);
        $filtered = $this->body();
        self::assertSame(['items', 'count'], array_keys($filtered));
        self::assertSame(self::TICKET_KEYS, array_keys($filtered['items'][0]));

        self::assertSame($unfiltered['items'][0], $filtered['items'][0]);
    }

    public function testAc9UrsN03OtherTicketEndpointsAreUntouched(): void
    {
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

    public function testAc10RepositoryExposesComposableQueryBuilderPieces(): void
    {
        $this->createTicket(title: 'open high old', priority: 'high', status: 'open', createdAt: $this->at('-3 hours'));
        $this->createTicket(title: 'open low', priority: 'low', status: 'open', createdAt: $this->at('-2 hours'));
        $this->createTicket(title: 'closed high', priority: 'high', status: 'closed', createdAt: $this->at('-1 hour'));
        $this->createTicket(title: 'open high new', priority: 'high', status: 'open', createdAt: $this->at('-30 minutes'));

        /** @var TicketRepository $repository */
        $repository = static::getContainer()->get(TicketRepository::class);

        $qb = $repository->createNewestFirstQueryBuilder();
        self::assertInstanceOf(QueryBuilder::class, $qb);

        $qb = $repository->withStatus($qb, 'open');
        self::assertStringContainsString('t.status = :status', $qb->getDQL());

        $results = $qb->andWhere('t.priority = :p')->setParameter('p', 'high')->getQuery()->getResult();
        self::assertCount(2, $results);
        $rows = array_map(static fn (Ticket $t) => $t->toArray(), $results);
        self::assertSame(['open high new', 'open high old'], array_column($rows, 'title'));
        self::assertSame(['open', 'open'], array_column($rows, 'status'));
        self::assertSame(['high', 'high'], array_column($rows, 'priority'));

        $all = $repository->findNewestFirst();
        $legacy = $repository->findAllNewestFirst();
        self::assertCount(4, $all);
        self::assertSame(
            array_map(static fn (Ticket $t) => $t->toArray(), $legacy),
            array_map(static fn (Ticket $t) => $t->toArray(), $all),
        );
    }
}
