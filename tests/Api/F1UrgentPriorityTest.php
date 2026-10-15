<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Ticket;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class F1UrgentPriorityTest extends WebTestCase
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

    public function testAc1PrioritiesConstant(): void
    {
        self::assertSame(['low', 'normal', 'high', 'urgent'], Ticket::PRIORITIES);
    }

    public function testAc2UrgentAccepted(): void
    {
        $this->postTicket('urgent');
        self::assertResponseStatusCodeSame(201);
        $created = $this->body();
        self::assertSame('urgent', $created['priority']);

        $this->client->request('GET', '/api/tickets/'.$created['id']);
        self::assertResponseStatusCodeSame(200);
        self::assertSame('urgent', $this->body()['priority']);
    }

    public function testAc3UnknownPriorityReturns422(): void
    {
        $this->postTicket('critical');
        self::assertResponseStatusCodeSame(422);
        $errors = $this->body()['errors'];
        self::assertIsString($errors['priority']);
        self::assertNotSame('', $errors['priority']);
        self::assertSame(0, $this->ticketCount());
    }

    public function testAc4CaseSensitiveRejected(): void
    {
        $this->postTicket('HIGH');
        self::assertResponseStatusCodeSame(422);
        self::assertArrayHasKey('priority', $this->body()['errors']);
        self::assertSame(0, $this->ticketCount());
    }

    public function testAc5NonStringPriorityIsClientError(): void
    {
        $this->postTicket(5);
        $status = $this->client->getResponse()->getStatusCode();
        self::assertGreaterThanOrEqual(400, $status);
        self::assertLessThan(500, $status);
        json_decode((string) $this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(0, $this->ticketCount());
    }

    public function testAc6MissingOrBlankPriorityReturns422(): void
    {
        $this->client->request('POST', '/api/tickets', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(['title' => 'T', 'description' => 'D']));
        self::assertResponseStatusCodeSame(422);
        self::assertArrayHasKey('priority', $this->body()['errors']);

        $this->postTicket('');
        self::assertResponseStatusCodeSame(422);
        self::assertArrayHasKey('priority', $this->body()['errors']);
    }

    public function testAc7ExistingPrioritiesStillAccepted(): void
    {
        foreach (['low', 'normal', 'high'] as $priority) {
            $this->postTicket($priority);
            self::assertResponseStatusCodeSame(201);
            self::assertSame($priority, $this->body()['priority']);
        }
    }

    public function testAc8EmptyStatsByPriority(): void
    {
        $this->client->request('GET', '/api/stats');
        self::assertResponseIsSuccessful();
        $stats = $this->body();
        self::assertSame(0, $stats['total']);
        self::assertSame(['low' => 0, 'normal' => 0, 'high' => 0, 'urgent' => 0, 'other' => 0], $stats['by_priority']);
    }

    public function testAc9StatsByPriority(): void
    {
        foreach (['low', 'normal', 'high', 'urgent', 'urgent'] as $priority) {
            $this->postTicket($priority);
            self::assertResponseStatusCodeSame(201);
        }
        $this->client->request('GET', '/api/stats');
        $stats = $this->body();
        self::assertSame(5, $stats['total']);
        self::assertSame(['low' => 1, 'normal' => 1, 'high' => 1, 'urgent' => 2, 'other' => 0], $stats['by_priority']);
    }

    public function testAc10OtherBucket(): void
    {
        $this->insertCritical();
        $this->postTicket('urgent');
        self::assertResponseStatusCodeSame(201);

        $this->client->request('GET', '/api/stats');
        $stats = $this->body();
        self::assertSame(1, $stats['by_priority']['other']);
        self::assertSame(1, $stats['by_priority']['urgent']);
        self::assertSame($stats['total'], array_sum($stats['by_priority']));
    }

    public function testAc11OutOfListRowReturnedUnchanged(): void
    {
        $id = $this->insertCritical();
        $this->client->request('GET', '/api/tickets/'.$id);
        self::assertResponseStatusCodeSame(200);
        self::assertSame('critical', $this->body()['priority']);
    }

    public function testAc12ExistingStatsKeysUnchanged(): void
    {
        $this->postTicket('low');
        $this->postTicket('high');
        $id = $this->body()['id'];
        $this->client->request('PATCH', "/api/tickets/$id/status", server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(['status' => 'closed']));

        $this->client->request('GET', '/api/stats');
        $stats = $this->body();
        self::assertSame(2, $stats['total']);
        self::assertSame(1, $stats['open']);
        self::assertSame(0, $stats['in_progress']);
        self::assertSame(1, $stats['closed']);
        self::assertEquals(50, $stats['closed_ratio']);
    }

    private function insertCritical(): int
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $ticket = new Ticket('Legacy', 'Old row', 'critical');
        $em->persist($ticket);
        $em->flush();

        return (int) $ticket->getId();
    }

    private function ticketCount(): int
    {
        $this->client->request('GET', '/api/tickets');

        return $this->body()['count'];
    }

    private function postTicket(mixed $priority): void
    {
        $this->client->request('POST', '/api/tickets', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(['title' => 'T', 'description' => 'D', 'priority' => $priority], JSON_THROW_ON_ERROR));
    }

    /** @return array<string, mixed> */
    private function body(): array
    {
        return json_decode((string) $this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }
}
