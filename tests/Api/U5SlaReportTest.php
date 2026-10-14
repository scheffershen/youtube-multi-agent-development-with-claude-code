<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Ticket;
use App\Repository\TicketRepository;
use App\Service\SlaReport;

/**
 * U5: GET /api/reports/sla per-priority totals, breached counts and breach rate.
 * One test method per acceptance criterion of specs/U5-sla-report.md.
 * AC10 is a service-level test on SlaReport and TicketRepository (fetched from the
 * container) and lives here because the schema is already reset in ApiTestCase::setUp().
 */
final class U5SlaReportTest extends ApiTestCase
{
    private const array KNOWN_PRIORITIES = ['low', 'normal', 'high', 'urgent'];
    private const array ENVELOPE_KEYS = ['generatedAt', 'priorities'];
    private const array ENTRY_KEYS = ['priority', 'total', 'breached', 'breach_rate'];
    private const array TICKET_KEYS = ['id', 'title', 'description', 'priority', 'status', 'createdAt', 'dueAt', 'assignee'];
    private const array STATS_KEYS = ['total', 'open', 'in_progress', 'closed', 'closed_ratio'];

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

    private function assertJsonContentType(string $message = ''): void
    {
        self::assertStringStartsWith('application/json', (string) $this->client->getResponse()->headers->get('Content-Type'), $message);
    }

    /** @param list<array<string, mixed>> $priorities @return array<string, mixed> */
    private static function entry(array $priorities, string $priority): array
    {
        foreach ($priorities as $entry) {
            if (($entry['priority'] ?? null) === $priority) {
                return $entry;
            }
        }
        self::fail(sprintf('No entry for priority "%s" in %s', $priority, json_encode($priorities)));
    }

    /** @param array<string, mixed> $entry */
    private static function assertEntryValues(array $entry, int $total, int $breached, float $breachRate, string $message = ''): void
    {
        self::assertSame($total, $entry['total'], $message.' total');
        self::assertSame($breached, $entry['breached'], $message.' breached');
        self::assertTrue(is_float($entry['breach_rate']), $message.' breach_rate must decode as a float, got '.var_export($entry['breach_rate'], true));
        self::assertSame($breachRate, $entry['breach_rate'], $message.' breach_rate');
    }

    /** Amended by R2 (D3): the empty report now has four zero entries, urgent fourth (D2). */
    public function testAc1UrsF0503EmptyTableReturns200WithFourZeroEntries(): void
    {
        $this->client->request('GET', '/api/reports/sla');
        self::assertResponseStatusCodeSame(200);
        $this->assertJsonContentType();

        $body = $this->body();
        self::assertSame(self::ENVELOPE_KEYS, array_keys($body));
        self::assertNotFalse(\DateTimeImmutable::createFromFormat(\DATE_ATOM, $body['generatedAt']), 'generatedAt must be DATE_ATOM');

        self::assertCount(4, $body['priorities']);
        self::assertSame(self::KNOWN_PRIORITIES, array_column($body['priorities'], 'priority'));
        foreach ($body['priorities'] as $entry) {
            self::assertSame(self::ENTRY_KEYS, array_keys($entry), $entry['priority'] ?? '?');
            self::assertSame(0, $entry['total'], $entry['priority']);
            self::assertSame(0, $entry['breached'], $entry['priority']);
            self::assertTrue(is_float($entry['breach_rate']), $entry['priority'].' breach_rate must be a float');
            self::assertSame(0.0, $entry['breach_rate'], $entry['priority']);
        }
    }

    /** Amended by R2 (D3): the exact row list gains the zero urgent row after high (D2). */
    public function testAc2UrsF0501TotalsBreachedAndRatesAreComputedPerPriority(): void
    {
        $this->createTicket(title: 'Low breached', priority: 'low', status: 'open', createdAt: $this->at('-3 hours'), dueAt: $this->at('-1 hour'));
        $this->createTicket(title: 'Low fine', priority: 'low', status: 'open', createdAt: $this->at('-2 hours'), dueAt: $this->at('+1 hour'));
        $this->createTicket(title: 'Normal breached', priority: 'normal', status: 'in_progress', createdAt: $this->at('-4 hours'), dueAt: $this->at('-2 hours'));
        $this->createTicket(title: 'Normal closed past due', priority: 'normal', status: 'closed', createdAt: $this->at('-3 hours'), dueAt: $this->at('-1 hour'));
        $this->createTicket(title: 'Normal fine', priority: 'normal', status: 'open', createdAt: $this->at('-1 hour'), dueAt: $this->at('+1 hour'));
        $this->createTicket(title: 'High breached', priority: 'high', status: 'open', createdAt: $this->at('-1 hour'), dueAt: $this->at('-30 minutes'));

        $this->client->request('GET', '/api/reports/sla');
        self::assertResponseStatusCodeSame(200);
        $body = $this->body();

        self::assertSame([
            ['priority' => 'low', 'total' => 2, 'breached' => 1, 'breach_rate' => 50.0],
            ['priority' => 'normal', 'total' => 3, 'breached' => 1, 'breach_rate' => 33.3],
            ['priority' => 'high', 'total' => 1, 'breached' => 1, 'breach_rate' => 100.0],
            ['priority' => 'urgent', 'total' => 0, 'breached' => 0, 'breach_rate' => 0.0],
        ], $body['priorities']);
        self::assertSame(6, array_sum(array_column($body['priorities'], 'total')));
    }

    public function testAc3UrsF0501RateIsRoundedToOneDecimalAndNoBreachIsZeroPointZero(): void
    {
        $this->createTicket(title: 'Low breached', priority: 'low', status: 'open', dueAt: $this->at('-1 hour'));
        for ($i = 1; $i <= 5; ++$i) {
            $this->createTicket(title: "Low fine $i", priority: 'low', status: 'open', dueAt: $this->at('+1 hour'));
        }
        $this->createTicket(title: 'Normal breached 1', priority: 'normal', status: 'open', dueAt: $this->at('-1 hour'));
        $this->createTicket(title: 'Normal breached 2', priority: 'normal', status: 'open', dueAt: $this->at('-1 hour'));
        $this->createTicket(title: 'Normal fine', priority: 'normal', status: 'open', dueAt: $this->at('+1 hour'));
        $this->createTicket(title: 'High closed past due', priority: 'high', status: 'closed', dueAt: $this->at('-1 hour'));

        $this->client->request('GET', '/api/reports/sla');
        self::assertResponseStatusCodeSame(200);
        $priorities = $this->body()['priorities'];

        self::assertEntryValues(self::entry($priorities, 'low'), 6, 1, 16.7, 'low');
        self::assertEntryValues(self::entry($priorities, 'normal'), 3, 2, 66.7, 'normal');
        self::assertEntryValues(self::entry($priorities, 'high'), 1, 0, 0.0, 'high');
    }

    /** Amended by R2 (D3): four known rows (urgent fourth, D2), still no other entry. */
    public function testAc4UrsF0502KnownPriorityWithNoTicketIsListedWithZeroRateAndNoOtherEntry(): void
    {
        $this->createTicket(title: 'High breached', priority: 'high', status: 'open', dueAt: $this->at('-1 hour'));

        $this->client->request('GET', '/api/reports/sla');
        self::assertResponseStatusCodeSame(200);
        $priorities = $this->body()['priorities'];

        self::assertCount(4, $priorities);
        self::assertSame(['priority' => 'low', 'total' => 0, 'breached' => 0, 'breach_rate' => 0.0], $priorities[0]);
        self::assertSame(['priority' => 'normal', 'total' => 0, 'breached' => 0, 'breach_rate' => 0.0], $priorities[1]);
        self::assertSame(['priority' => 'high', 'total' => 1, 'breached' => 1, 'breach_rate' => 100.0], $priorities[2]);
    }

    public function testAc5BreachDefinitionMatchesU4Filter(): void
    {
        $this->createTicket(title: 'Breached', priority: 'normal', status: 'open', createdAt: $this->at('-4 hours'), dueAt: $this->at('-2 hours'));
        $this->createTicket(title: 'Closed past due', priority: 'normal', status: 'closed', createdAt: $this->at('-3 hours'), dueAt: $this->at('-1 hour'));
        $this->createTicket(title: 'Not yet due', priority: 'normal', status: 'open', createdAt: $this->at('-2 hours'), dueAt: $this->at('+1 hour'));
        $legacy = $this->createTicket(title: 'Legacy null due', priority: 'normal', status: 'open', createdAt: $this->at('-1 hour'));
        $this->nullDueAt($legacy);

        $this->client->request('GET', '/api/reports/sla');
        self::assertResponseStatusCodeSame(200);
        $normal = self::entry($this->body()['priorities'], 'normal');
        self::assertEntryValues($normal, 4, 1, 25.0, 'normal');

        $this->client->request('GET', '/api/tickets?breached=true');
        self::assertResponseStatusCodeSame(200);
        self::assertSame(1, $this->body()['count']);
        self::assertSame($normal['breached'], $this->body()['count'], 'report breached must equal the U4 filter count');

        $this->client->request('GET', '/api/tickets');
        self::assertResponseStatusCodeSame(200);
        self::assertSame(4, $this->body()['count']);
        self::assertSame($normal['total'], $this->body()['count'], 'report total must equal the unfiltered count');
    }

    public function testAc6UnknownPrioritiesAreAggregatedUnderTrailingOtherEntry(): void
    {
        $this->createTicket(title: 'Escalated breached', priority: 'escalated', status: 'open', createdAt: $this->at('-2 hours'), dueAt: $this->at('-1 hour'));
        $this->createTicket(title: 'Escalated fine', priority: 'escalated', status: 'open', createdAt: $this->at('-1 hour'), dueAt: $this->at('+1 hour'));
        $this->createTicket(title: 'Critical closed past due', priority: 'critical', status: 'closed', createdAt: $this->at('-1 hour'), dueAt: $this->at('-1 hour'));
        $this->createTicket(title: 'Normal fine', priority: 'normal', status: 'open', createdAt: $this->at('-1 hour'), dueAt: $this->at('+1 hour'));

        $this->client->request('GET', '/api/reports/sla');
        self::assertResponseStatusCodeSame(200);
        $priorities = $this->body()['priorities'];

        self::assertCount(5, $priorities);
        self::assertSame(['low', 'normal', 'high', 'urgent', 'other'], array_column($priorities, 'priority'));
        self::assertSame(['priority' => 'other', 'total' => 3, 'breached' => 1, 'breach_rate' => 33.3], $priorities[4]);
        self::assertEntryValues($priorities[1], 1, 0, 0.0, 'normal');
        self::assertSame(4, array_sum(array_column($priorities, 'total')));
    }

    public function testAc7UrsN02AnyQueryStringIsIgnoredAndNever500OrHtml(): void
    {
        $this->createTicket(title: 'Normal breached', priority: 'normal', status: 'open', dueAt: $this->at('-1 hour'));

        $this->client->request('GET', '/api/reports/sla');
        self::assertResponseStatusCodeSame(200);
        $reference = $this->body()['priorities'];

        foreach (['?breached=maybe', '?status=done', '?priority[]=low', '?now[a]=1', '?now=garbage'] as $query) {
            $uri = '/api/reports/sla'.$query;
            $this->client->request('GET', $uri);
            self::assertResponseStatusCodeSame(200, $uri);
            $this->assertJsonContentType($uri);
            $body = $this->body();
            self::assertSame(self::ENVELOPE_KEYS, array_keys($body), $uri);
            self::assertSame($reference, $body['priorities'], $uri);
        }
    }

    public function testAc8UrsN02WrongMethodIsJson405NotHtmlNot500(): void
    {
        $this->postJson('/api/reports/sla', []);
        self::assertResponseStatusCodeSame(405);
        $this->assertJsonContentType('POST');
        $body = $this->body();
        self::assertArrayHasKey('error', $body);
        self::assertIsString($body['error']);
        self::assertStringContainsString('GET', (string) $this->client->getResponse()->headers->get('Allow'), 'Allow header must list GET');

        $this->client->request('DELETE', '/api/reports/sla');
        self::assertResponseStatusCodeSame(405);
        $this->assertJsonContentType('DELETE');
    }

    public function testAc9UrsN03ExistingEndpointsAreUntouched(): void
    {
        $this->createTicket(title: 'Low breached', priority: 'low', status: 'open', dueAt: $this->at('-1 hour'));

        $this->client->request('GET', '/api/stats');
        self::assertResponseStatusCodeSame(200);
        $stats = $this->body();
        self::assertSame(self::STATS_KEYS, array_keys($stats));
        self::assertSame(1, $stats['total']);
        self::assertEquals(0, $stats['closed_ratio']);

        $this->client->request('GET', '/api/tickets');
        self::assertResponseStatusCodeSame(200);
        $list = $this->body();
        self::assertSame(['items', 'count'], array_keys($list));
        self::assertSame(self::TICKET_KEYS, array_keys($list['items'][0]));

        $this->client->request('GET', '/api/tickets?breached=true');
        self::assertResponseStatusCodeSame(200);
        self::assertSame(1, $this->body()['count']);

        $this->client->request('GET', '/api/tickets?breached=maybe');
        self::assertResponseStatusCodeSame(400);
        self::assertSame('Invalid breached', $this->body()['error']);

        $this->postJson('/api/tickets', ['title' => 'Untouched', 'description' => 'd', 'priority' => 'normal']);
        self::assertResponseStatusCodeSame(201);
    }

    /** Amended by R2 (D3): the empty report has four zero entries (urgent fourth, D2). */
    public function testAc10ServiceAndRepositoryCountThroughSharedClockPinnedPredicate(): void
    {
        /** @var TicketRepository $repository */
        $repository = static::getContainer()->get(TicketRepository::class);
        /** @var SlaReport $service */
        $service = static::getContainer()->get(SlaReport::class);

        // Empty table: empty maps, four zero entries.
        self::assertSame([], $repository->countByPriority());
        self::assertSame([], $repository->countByPriority(true, new \DateTimeImmutable()));
        $empty = $service->generate()['priorities'];
        self::assertCount(4, $empty);
        foreach ($empty as $entry) {
            self::assertSame(0, $entry['total'], $entry['priority']);
        }

        $this->createTicket(title: 'Normal not yet due', priority: 'normal', status: 'open', createdAt: $this->at('-1 hour'), dueAt: $this->at('+1 hour'));
        $this->createTicket(title: 'High closed past due', priority: 'high', status: 'closed', createdAt: $this->at('-1 hour'), dueAt: $this->at('-2 hours'));

        // (a) totals by priority, order of the map unspecified, values are ints.
        $totals = $repository->countByPriority();
        self::assertEqualsCanonicalizing(['normal', 'high'], array_keys($totals));
        self::assertEqualsCanonicalizing(['normal' => 1, 'high' => 1], $totals);
        foreach ($totals as $priority => $count) {
            self::assertTrue(is_int($count), "$priority count must be an int");
        }

        // (b) nothing breached at the real clock.
        self::assertSame([], $repository->countByPriority(true, new \DateTimeImmutable()));

        // (c) pinning the clock forward breaches the normal ticket; the closed high one stays unbreached.
        self::assertSame(['normal' => 1], $repository->countByPriority(true, $this->at('+2 hours')));

        // (d) the complement at the same pinned clock.
        self::assertSame(['high' => 1], $repository->countByPriority(false, $this->at('+2 hours')));

        // (e) the service honours the pinned clock and formats it into generatedAt.
        $now = $this->at('+2 hours');
        $report = $service->generate($now);
        self::assertSame($now->format(\DATE_ATOM), $report['generatedAt']);
        $pinnedNormal = self::entry($report['priorities'], 'normal');
        self::assertSame(1, $pinnedNormal['total']);
        self::assertSame(1, $pinnedNormal['breached']);
        self::assertSame(100.0, $pinnedNormal['breach_rate']);
        $pinnedHigh = self::entry($report['priorities'], 'high');
        self::assertSame(1, $pinnedHigh['total']);
        self::assertSame(0, $pinnedHigh['breached']);
        self::assertSame(0.0, $pinnedHigh['breach_rate']);

        $unpinnedNormal = self::entry($service->generate()['priorities'], 'normal');
        self::assertSame(0, $unpinnedNormal['breached']);
        self::assertSame(0.0, $unpinnedNormal['breach_rate']);

        // (f) priorities is a list in Ticket::PRIORITIES order.
        $priorities = $service->generate($now)['priorities'];
        self::assertTrue(array_is_list($priorities));
        self::assertSame(self::KNOWN_PRIORITIES, array_column($priorities, 'priority'));
    }
}
