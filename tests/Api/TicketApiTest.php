<?php

declare(strict_types=1);

namespace App\Tests\Api;

/**
 * Baseline suite: the happy paths that already work.
 * The tester agent extends this file (or adds siblings) for every spec it receives.
 */
final class TicketApiTest extends ApiTestCase
{
    public function testCreateAndListTicket(): void
    {
        $this->postJson('/api/tickets', [
            'title' => 'Printer on fire',
            'description' => 'Literally.',
            'priority' => 'high',
        ]);
        self::assertResponseStatusCodeSame(201);
        $created = $this->body();
        self::assertSame('open', $created['status']);

        $this->client->request('GET', '/api/tickets');
        self::assertResponseIsSuccessful();
        $list = $this->body();
        self::assertSame(1, $list['count']);
        self::assertSame('Printer on fire', $list['items'][0]['title']);
    }

    public function testChangeStatus(): void
    {
        $this->postJson('/api/tickets', ['title' => 'Slow search', 'description' => 'Takes 4s.', 'priority' => 'normal']);
        $id = $this->body()['id'];

        $this->client->request('PATCH', "/api/tickets/$id/status", server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(['status' => 'closed']));
        self::assertResponseIsSuccessful();
        self::assertSame('closed', $this->body()['status']);
    }

    public function testStatsWithTickets(): void
    {
        $this->postJson('/api/tickets', ['title' => 'A', 'description' => 'a', 'priority' => 'low']);

        $this->client->request('GET', '/api/stats');
        self::assertResponseIsSuccessful();
        $stats = $this->body();
        self::assertSame(1, $stats['total']);
        self::assertEquals(0, $stats['closed_ratio']);
    }
}
