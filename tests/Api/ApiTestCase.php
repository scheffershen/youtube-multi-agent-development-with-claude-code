<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Agent;
use App\Entity\Ticket;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Shared base for HTTP-level tests: fresh schema per test, JSON helpers and entity factories.
 */
abstract class ApiTestCase extends WebTestCase
{
    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        // Fresh schema for every test: the test DB is a throwaway SQLite file.
        $em = $this->em();
        $tool = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    protected function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    /** @param array<string, mixed> $payload */
    protected function postJson(string $uri, array $payload): void
    {
        $this->client->request('POST', $uri, server: ['CONTENT_TYPE' => 'application/json'], content: json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /** @param array<string, mixed> $payload */
    protected function patchJson(string $uri, array $payload): void
    {
        $this->client->request('PATCH', $uri, server: ['CONTENT_TYPE' => 'application/json'], content: json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /** @return array<string, mixed> */
    protected function body(): array
    {
        return json_decode((string) $this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }

    protected function createAgent(string $name = 'Ada', string $email = 'ada@example.test', bool $active = true): Agent
    {
        $agent = new Agent($name, $email, $active);
        $em = $this->em();
        $em->persist($agent);
        $em->flush();

        return $agent;
    }

    protected function createTicket(string $title = 'Ticket', string $description = 'Body', string $priority = 'normal', ?\DateTimeImmutable $createdAt = null, ?\DateTimeImmutable $dueAt = null, ?Agent $assignee = null, string $status = 'open'): Ticket
    {
        $ticket = new Ticket($title, $description, $priority, $createdAt, $dueAt);
        $ticket->setAssignee($assignee);
        $ticket->setStatus($status);
        $em = $this->em();
        $em->persist($ticket);
        $em->flush();

        return $ticket;
    }
}
