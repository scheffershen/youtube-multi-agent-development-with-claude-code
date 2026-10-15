<?php

declare(strict_types=1);

namespace App\Tests\Api;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class F2FilterTicketsByStatusTest extends WebTestCase
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

    public function testAc1FilterOpenReturnsOnlyOpen(): void
    {
        $this->seedMixed();

        $this->client->request('GET', '/api/tickets?status=open');
        self::assertResponseStatusCodeSame(200);
        $list = $this->body();
        self::assertSame(1, $list['count']);
        self::assertCount(1, $list['items']);
        foreach ($list['items'] as $item) {
            self::assertSame('open', $item['status']);
        }
    }

    public function testAc2FilterClosedAndInProgress(): void
    {
        $this->seedMixed();

        $this->client->request('GET', '/api/tickets?status=closed');
        self::assertResponseStatusCodeSame(200);
        $list = $this->body();
        self::assertSame(1, $list['count']);
        self::assertSame('closed', $list['items'][0]['status']);

        $this->client->request('GET', '/api/tickets?status=in_progress');
        self::assertResponseStatusCodeSame(200);
        $list = $this->body();
        self::assertSame(1, $list['count']);
        self::assertSame('in_progress', $list['items'][0]['status']);
    }

    public function testAc3NoParameterReturnsAll(): void
    {
        $this->seedMixed();

        $this->client->request('GET', '/api/tickets');
        self::assertResponseStatusCodeSame(200);
        self::assertSame(3, $this->body()['count']);
    }

    public function testAc4EmptySchemaKnownStatusReturnsEmptyList(): void
    {
        $this->client->request('GET', '/api/tickets?status=open');
        self::assertResponseStatusCodeSame(200);
        $list = $this->body();
        self::assertSame([], $list['items']);
        self::assertSame(0, $list['count']);
    }

    public function testAc5KnownStatusWithoutMatchReturnsEmptyList(): void
    {
        $this->createTicket('A');
        $this->createTicket('B');

        $this->client->request('GET', '/api/tickets?status=closed');
        self::assertResponseStatusCodeSame(200);
        $list = $this->body();
        self::assertSame([], $list['items']);
        self::assertSame(0, $list['count']);
    }

    public function testAc6UnknownStatusReturns400(): void
    {
        $this->client->request('GET', '/api/tickets?status=bogus');
        self::assertResponseStatusCodeSame(400);
        $body = $this->body();
        self::assertArrayNotHasKey('items', $body);
        self::assertIsString($body['errors']['status']);
        self::assertNotSame('', $body['errors']['status']);
        foreach (['open', 'in_progress', 'closed'] as $allowed) {
            self::assertStringContainsString($allowed, $body['errors']['status']);
        }
    }

    public function testAc7UppercaseStatusReturns400(): void
    {
        $this->client->request('GET', '/api/tickets?status=OPEN');
        self::assertResponseStatusCodeSame(400);
        self::assertArrayHasKey('status', $this->body()['errors']);
    }

    public function testAc8EmptyStatusReturns400(): void
    {
        $this->client->request('GET', '/api/tickets?status=');
        self::assertResponseStatusCodeSame(400);
        $errors = $this->body()['errors'];
        self::assertIsString($errors['status']);
        self::assertNotSame('', $errors['status']);
    }

    public function testAc9UnknownStatusWithTicketsIsNotAList(): void
    {
        $this->seedMixed();

        $this->client->request('GET', '/api/tickets?status=bogus');
        self::assertResponseStatusCodeSame(400);
        $body = $this->body();
        self::assertArrayNotHasKey('items', $body);
        self::assertArrayNotHasKey('count', $body);
        self::assertArrayHasKey('status', $body['errors']);
    }

    public function testAc10PostEmptyBodyStillReturns422(): void
    {
        $this->postJson('/api/tickets', []);
        self::assertResponseStatusCodeSame(422);
        $errors = $this->body()['errors'];
        self::assertArrayHasKey('title', $errors);
        self::assertArrayHasKey('description', $errors);
        self::assertArrayHasKey('priority', $errors);
    }

    public function testAc11PatchBogusStatusStillReturns422(): void
    {
        $id = $this->createTicket('A');

        $this->client->request('PATCH', "/api/tickets/$id/status", server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(['status' => 'bogus']));
        self::assertResponseStatusCodeSame(422);
        $body = $this->body();
        self::assertArrayHasKey('error', $body);
        self::assertArrayHasKey('allowed', $body);
    }

    // AC12: covered by the existing, unmodified tests in TicketApiTest.

    private function seedMixed(): void
    {
        $this->createTicket('A');
        $closed = $this->createTicket('B');
        $progress = $this->createTicket('C');
        $this->patchStatus($closed, 'closed');
        $this->patchStatus($progress, 'in_progress');
    }

    private function createTicket(string $title): int
    {
        $this->postJson('/api/tickets', ['title' => $title, 'description' => 'd', 'priority' => 'low']);
        self::assertResponseStatusCodeSame(201);

        return $this->body()['id'];
    }

    private function patchStatus(int $id, string $status): void
    {
        $this->client->request('PATCH', "/api/tickets/$id/status", server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(['status' => $status]));
        self::assertResponseIsSuccessful();
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
