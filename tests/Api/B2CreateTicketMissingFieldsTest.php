<?php

declare(strict_types=1);

namespace App\Tests\Api;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Spec B2: missing title, description or priority on POST /api/tickets returns 422.
 */
final class B2CreateTicketMissingFieldsTest extends WebTestCase
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

    public function testAc1MissingPriorityReturns422(): void
    {
        $this->postJson(['title' => 'T', 'description' => 'D']);
        self::assertResponseStatusCodeSame(422);
        $this->assertErrorString($this->body(), 'priority');
        $this->assertNothingPersisted();
    }

    public function testAc2MissingTitleReturns422(): void
    {
        $this->postJson(['description' => 'D', 'priority' => 'low']);
        self::assertResponseStatusCodeSame(422);
        $this->assertErrorString($this->body(), 'title');
        $this->assertNothingPersisted();
    }

    public function testAc3MissingDescriptionReturns422(): void
    {
        $this->postJson(['title' => 'T', 'priority' => 'low']);
        self::assertResponseStatusCodeSame(422);
        $this->assertErrorString($this->body(), 'description');
        $this->assertNothingPersisted();
    }

    public function testAc4EmptyObjectReportsAllThreeFields(): void
    {
        $this->postJson([]);
        self::assertResponseStatusCodeSame(422);
        $body = $this->body();
        self::assertArrayHasKey('errors', $body);
        $keys = array_keys($body['errors']);
        sort($keys);
        self::assertSame(['description', 'priority', 'title'], $keys);
        foreach ($keys as $key) {
            $this->assertErrorString($body, $key);
        }
        $this->assertNothingPersisted();
    }

    public function testAc5EmptyRequestBodyReturns422(): void
    {
        $this->client->request('POST', '/api/tickets', server: ['CONTENT_TYPE' => 'application/json'], content: '');
        self::assertResponseStatusCodeSame(422);
        $body = $this->body();
        self::assertArrayHasKey('errors', $body);
        $keys = array_keys($body['errors']);
        sort($keys);
        self::assertSame(['description', 'priority', 'title'], $keys);
        $this->assertNothingPersisted();
    }

    public function testAc6EmptyTitleOnlyReportsTitle(): void
    {
        $this->postJson(['title' => '', 'description' => 'D', 'priority' => 'low']);
        self::assertResponseStatusCodeSame(422);
        $body = $this->body();
        $this->assertErrorString($body, 'title');
        self::assertArrayNotHasKey('description', $body['errors']);
        self::assertArrayNotHasKey('priority', $body['errors']);
        $this->assertNothingPersisted();
    }

    public function testAc7NullPriorityReturns422(): void
    {
        $this->postJson(['title' => 'T', 'description' => 'D', 'priority' => null]);
        self::assertResponseStatusCodeSame(422);
        $this->assertErrorString($this->body(), 'priority');
        $this->assertNothingPersisted();
    }

    public function testAc8NothingPersistedAfterAnyFailure(): void
    {
        $payloads = [
            ['title' => 'T', 'description' => 'D'],
            ['description' => 'D', 'priority' => 'low'],
            ['title' => 'T', 'priority' => 'low'],
            [],
            ['title' => '', 'description' => 'D', 'priority' => 'low'],
            ['title' => 'T', 'description' => 'D', 'priority' => null],
        ];
        foreach ($payloads as $payload) {
            $this->postJson($payload);
            self::assertResponseStatusCodeSame(422);
        }
        $this->client->request('POST', '/api/tickets', server: ['CONTENT_TYPE' => 'application/json'], content: '');
        self::assertResponseStatusCodeSame(422);
        $this->assertNothingPersisted();
    }

    public function testAc9ValidBodyStillReturns201(): void
    {
        $this->postJson(['title' => 'T', 'description' => 'D', 'priority' => 'normal']);
        self::assertResponseStatusCodeSame(201);
        $body = $this->body();
        self::assertSame('open', $body['status']);
        foreach (['id', 'title', 'description', 'priority', 'status', 'createdAt'] as $key) {
            self::assertArrayHasKey($key, $body);
        }
    }

    public function testAc11PatchBogusStatusKeepsErrorShape(): void
    {
        $this->postJson(['title' => 'T', 'description' => 'D', 'priority' => 'low']);
        $id = $this->body()['id'];

        $this->client->request('PATCH', "/api/tickets/$id/status", server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(['status' => 'bogus']));
        self::assertResponseStatusCodeSame(422);
        $body = $this->body();
        self::assertArrayHasKey('error', $body);
        self::assertArrayHasKey('allowed', $body);
    }

    /** @param array<string, mixed> $payload */
    private function postJson(array $payload): void
    {
        $this->client->request('POST', '/api/tickets', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /** @return array<string, mixed> */
    private function body(): array
    {
        return json_decode((string) $this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }

    /** @param array<string, mixed> $body */
    private function assertErrorString(array $body, string $field): void
    {
        self::assertArrayHasKey('errors', $body);
        self::assertArrayHasKey($field, $body['errors']);
        self::assertIsString($body['errors'][$field]);
        self::assertNotSame('', $body['errors'][$field]);
    }

    private function assertNothingPersisted(): void
    {
        $this->client->request('GET', '/api/tickets');
        self::assertResponseIsSuccessful();
        self::assertSame(0, $this->body()['count']);
    }
}
