<?php

declare(strict_types=1);

namespace App\Tests\Api;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * B3: GET /api/tickets/{id} returns 404 JSON for an unknown id.
 */
final class B3ShowTicketNotFoundTest extends WebTestCase
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

    public function testAc1UnknownIdReturns404(): void
    {
        $this->client->request('GET', '/api/tickets/9999');
        self::assertResponseStatusCodeSame(404);
    }

    public function testAc2UnknownIdReturnsJsonErrorBody(): void
    {
        $this->client->request('GET', '/api/tickets/9999');
        self::assertResponseStatusCodeSame(404);
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertSame(['error' => 'Ticket not found'], $this->body());
    }

    public function testAc3ExistingTicketReturns200WithShape(): void
    {
        $this->postJson('/api/tickets', [
            'title' => 'Printer on fire',
            'description' => 'Literally.',
            'priority' => 'high',
        ]);
        self::assertResponseStatusCodeSame(201);
        $created = $this->body();

        $this->client->request('GET', '/api/tickets/'.$created['id']);
        self::assertResponseStatusCodeSame(200);
        $shown = $this->body();
        foreach (['id', 'title', 'description', 'priority', 'status', 'createdAt'] as $key) {
            self::assertArrayHasKey($key, $shown);
            self::assertSame($created[$key], $shown[$key]);
        }
    }

    public function testAc4UnknownIdNextToExistingDataReturns404(): void
    {
        $this->postJson('/api/tickets', ['title' => 'A', 'description' => 'a', 'priority' => 'low']);
        self::assertResponseStatusCodeSame(201);
        $id = $this->body()['id'];

        $this->client->request('GET', '/api/tickets/'.($id + 1000));
        self::assertResponseStatusCodeSame(404);
    }

    public function testAc5PatchUnknownIdStillReturns404(): void
    {
        $this->client->request('PATCH', '/api/tickets/9999/status', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(['status' => 'closed']));
        self::assertResponseStatusCodeSame(404);
        self::assertSame(['error' => 'Ticket not found'], $this->body());
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
