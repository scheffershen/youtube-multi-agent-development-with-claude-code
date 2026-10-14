<?php

declare(strict_types=1);

namespace App\Tests\Api;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Spec B1: GET /api/stats must return zero stats (HTTP 200) instead of a 500
 * DivisionByZeroError when the ticket table is empty.
 */
final class B1StatsZeroTicketsTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        // Fresh schema for every test: the test DB is a throwaway SQLite file.
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $tool = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    public function testAc1EmptyTableReturns200(): void
    {
        $this->client->request('GET', '/api/stats');

        self::assertResponseStatusCodeSame(200);
        self::assertResponseHeaderSame('Content-Type', 'application/json');
    }

    public function testAc2EmptyTableBodyIsAllZeros(): void
    {
        $this->client->request('GET', '/api/stats');

        self::assertResponseStatusCodeSame(200);
        $stats = $this->body();

        self::assertSame(['total', 'open', 'in_progress', 'closed', 'closed_ratio'], array_keys($stats));
        self::assertSame(0, $stats['total']);
        self::assertSame(0, $stats['open']);
        self::assertSame(0, $stats['in_progress']);
        self::assertSame(0, $stats['closed']);
        self::assertNotNull($stats['closed_ratio']);
        self::assertIsNotString($stats['closed_ratio']);
        self::assertEquals(0, $stats['closed_ratio']);
    }

    public function testAc3RegressionNoClosedTickets(): void
    {
        $this->postJson('/api/tickets', ['title' => 'A', 'description' => 'a', 'priority' => 'low']);
        self::assertResponseStatusCodeSame(201);

        $this->client->request('GET', '/api/stats');

        self::assertResponseStatusCodeSame(200);
        $stats = $this->body();
        self::assertSame(1, $stats['total']);
        self::assertSame(1, $stats['open']);
        self::assertSame(0, $stats['in_progress']);
        self::assertSame(0, $stats['closed']);
        self::assertEquals(0, $stats['closed_ratio']);
    }

    public function testAc4RegressionMixedStatuses(): void
    {
        $this->postJson('/api/tickets', ['title' => 'First', 'description' => 'one', 'priority' => 'low']);
        self::assertResponseStatusCodeSame(201);
        $firstId = $this->body()['id'];

        $this->postJson('/api/tickets', ['title' => 'Second', 'description' => 'two', 'priority' => 'normal']);
        self::assertResponseStatusCodeSame(201);

        $this->client->request(
            'PATCH',
            "/api/tickets/$firstId/status",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['status' => 'closed'], JSON_THROW_ON_ERROR),
        );
        self::assertResponseIsSuccessful();
        self::assertSame('closed', $this->body()['status']);

        $this->client->request('GET', '/api/stats');

        self::assertResponseStatusCodeSame(200);
        $stats = $this->body();
        self::assertSame(['total', 'open', 'in_progress', 'closed', 'closed_ratio'], array_keys($stats));
        self::assertSame(2, $stats['total']);
        self::assertSame(1, $stats['open']);
        self::assertSame(0, $stats['in_progress']);
        self::assertSame(1, $stats['closed']);
        self::assertEquals(50.0, $stats['closed_ratio']);
    }

    public function testAc5RecoveryAfterEmptyTable(): void
    {
        // Empty table first (AC1 path).
        $this->client->request('GET', '/api/stats');
        self::assertResponseStatusCodeSame(200);
        self::assertSame(0, $this->body()['total']);

        // Then one ticket is created...
        $this->postJson('/api/tickets', ['title' => 'Recovered', 'description' => 'r', 'priority' => 'low']);
        self::assertResponseStatusCodeSame(201);

        // ...and the stats must reflect it, not a cached/poisoned empty result.
        $this->client->request('GET', '/api/stats');
        self::assertResponseStatusCodeSame(200);
        $stats = $this->body();
        self::assertSame(1, $stats['total']);
    }

    /** @param array<string, mixed> $payload */
    private function postJson(string $uri, array $payload): void
    {
        $this->client->request('POST', $uri, server: ['CONTENT_TYPE' => 'application/json'], content: json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /** @return array<string, mixed> */
    private function body(): array
    {
        return json_decode((string) $this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }
}
