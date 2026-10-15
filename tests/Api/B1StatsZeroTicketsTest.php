<?php

declare(strict_types=1);

namespace App\Tests\Api;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class B1StatsZeroTicketsTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $tool = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    public function testAc1EmptySchemaReturns200WithZeroCounts(): void
    {
        $this->client->request('GET', '/api/stats');
        self::assertResponseStatusCodeSame(200);
        $stats = $this->body();
        self::assertSame(0, $stats['total']);
        self::assertSame(0, $stats['open']);
        self::assertSame(0, $stats['in_progress']);
        self::assertSame(0, $stats['closed']);
    }

    public function testAc2EmptySchemaClosedRatioIsNumericZero(): void
    {
        $this->client->request('GET', '/api/stats');
        self::assertResponseStatusCodeSame(200);
        $stats = $this->body();
        self::assertArrayHasKey('closed_ratio', $stats);
        self::assertIsNumeric($stats['closed_ratio']);
        self::assertTrue(\is_int($stats['closed_ratio']) || \is_float($stats['closed_ratio']));
        self::assertEquals(0, $stats['closed_ratio']);
    }

    public function testAc3OneOfTwoClosedGivesFiftyPercent(): void
    {
        $this->postJson('/api/tickets', ['title' => 'A', 'description' => 'a', 'priority' => 'low']);
        $first = $this->body()['id'];
        $this->postJson('/api/tickets', ['title' => 'B', 'description' => 'b', 'priority' => 'low']);

        $this->client->request('PATCH', "/api/tickets/$first/status", server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(['status' => 'closed']));
        self::assertResponseIsSuccessful();

        $this->client->request('GET', '/api/stats');
        self::assertResponseStatusCodeSame(200);
        $stats = $this->body();
        self::assertSame(2, $stats['total']);
        self::assertSame(1, $stats['closed']);
        self::assertSame(1, $stats['open']);
        self::assertEquals(50, $stats['closed_ratio']);
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
