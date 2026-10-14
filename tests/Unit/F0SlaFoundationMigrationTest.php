<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Spec: specs/F0-sla-foundation.md, AC1.
 *
 * Not an HTTP-level test: it runs `bin/console doctrine:migrations:migrate -n` as a real
 * subprocess against (a) a copy of the dev database that already holds rows from the
 * previous schema and (b) a fresh, empty SQLite file, then inspects the result with PDO.
 */
final class F0SlaFoundationMigrationTest extends TestCase
{
    private string $projectDir;

    /** @var list<string> */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        $this->projectDir = \dirname(__DIR__, 2);
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }

    /**
     * AC1 (part 1): migrating a database that already holds rows from the previous schema
     * (three tickets, priorities high / low / urgent, as in the dev database) keeps every
     * row and column value, adds assignee_id / due_at as NULL and creates `agent` with a
     * unique index on email.
     */
    public function testAc1MigrateKeepsExistingDevRows(): void
    {
        $copy = $this->tempDbPath('previous-schema');
        self::assertFileDoesNotExist($copy);

        // Previous schema only: the original migration, then the three dev-style rows.
        $this->migrate($copy, 'DoctrineMigrations\\Version20261014130059');
        $this->insertPreviousSchemaRows($copy, [
            ['Login page shows blank screen', 'Blank after submit.', 'high', 'in_progress', '2026-10-14 13:11:19'],
            ['Typo on pricing page', 'Teh instead of the.', 'low', 'open', '2026-10-14 13:11:19'],
            ['Payment double charged', 'Charged twice.', 'urgent', 'open', '2026-10-14 13:11:19'],
        ]);

        $before = $this->fetchAll($copy, 'SELECT id, title, priority, status, created_at FROM ticket ORDER BY id');
        self::assertCount(3, $before);
        self::assertContains('urgent', array_column($before, 'priority'));

        $this->migrate($copy);

        $after = $this->fetchAll($copy, 'SELECT id, title, priority, status, created_at, assignee_id, due_at FROM ticket ORDER BY id');
        self::assertCount(3, $after);
        foreach ($after as $i => $row) {
            self::assertSame($before[$i]['id'], $row['id']);
            self::assertSame($before[$i]['title'], $row['title']);
            self::assertSame($before[$i]['priority'], $row['priority']);
            self::assertSame($before[$i]['status'], $row['status']);
            self::assertSame($before[$i]['created_at'], $row['created_at']);
            self::assertNull($row['assignee_id']);
            self::assertNull($row['due_at']);
        }

        $this->assertAgentTableWithUniqueEmail($copy);
    }

    /**
     * AC1 (part 2): both migrations run in order on an empty SQLite file.
     */
    public function testAc1MigrateRunsBothMigrationsOnEmptyDatabase(): void
    {
        $fresh = $this->tempDbPath('fresh');
        self::assertFileDoesNotExist($fresh);

        $this->migrate($fresh);

        $versions = $this->fetchAll($fresh, 'SELECT version FROM doctrine_migration_versions ORDER BY version');
        self::assertCount(2, $versions, 'exactly two migrations (the original one plus the F0 one) must have run');
        self::assertSame('DoctrineMigrations\\Version20261014130059', $versions[0]['version']);

        $columns = array_column($this->fetchAll($fresh, 'PRAGMA table_info(ticket)'), 'name');
        foreach (['id', 'title', 'description', 'priority', 'status', 'created_at', 'assignee_id', 'due_at'] as $column) {
            self::assertContains($column, $columns);
        }
        self::assertSame([], $this->fetchAll($fresh, 'SELECT id FROM ticket'));

        $this->assertAgentTableWithUniqueEmail($fresh);
    }

    private function assertAgentTableWithUniqueEmail(string $dbPath): void
    {
        $tables = array_column($this->fetchAll($dbPath, "SELECT name FROM sqlite_master WHERE type = 'table'"), 'name');
        self::assertContains('agent', $tables, 'table `agent` must exist');

        $uniqueOnEmail = false;
        foreach ($this->fetchAll($dbPath, 'PRAGMA index_list(agent)') as $index) {
            if ((int) $index['unique'] !== 1) {
                continue;
            }
            $indexColumns = array_column($this->fetchAll($dbPath, sprintf('PRAGMA index_info(%s)', $this->quoteIdentifier($index['name']))), 'name');
            if ($indexColumns === ['email']) {
                $uniqueOnEmail = true;
            }
        }
        self::assertTrue($uniqueOnEmail, '`agent` must have a unique index on `email`');
    }

    /** @param list<array{string, string, string, string, string}> $rows title, description, priority, status, created_at */
    private function insertPreviousSchemaRows(string $dbPath, array $rows): void
    {
        $pdo = new \PDO('sqlite:'.$dbPath);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $statement = $pdo->prepare('INSERT INTO ticket (title, description, priority, status, created_at) VALUES (?, ?, ?, ?, ?)');
        foreach ($rows as $row) {
            $statement->execute($row);
        }
        $pdo = null;
    }

    /** @param string|null $version migrate up to this version only; null means latest */
    private function migrate(string $dbPath, ?string $version = null): void
    {
        $ini = php_ini_loaded_file();
        $command = [\PHP_BINARY];
        if (\is_string($ini) && '' !== $ini) {
            $command[] = '-c';
            $command[] = $ini;
        }
        $command = array_merge($command, [$this->projectDir.'/bin/console', 'doctrine:migrations:migrate']);
        if (null !== $version) {
            $command[] = $version;
        }
        $command[] = '-n';

        $env = array_merge(getenv(), [
            'APP_ENV' => 'dev',
            'APP_DEBUG' => '1',
            'DATABASE_URL' => 'sqlite:///'.str_replace('\\', '/', $dbPath),
        ]);

        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open($command, $descriptors, $pipes, $this->projectDir, $env);
        self::assertIsResource($process, 'could not start bin/console');

        fclose($pipes[0]);
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        self::assertSame(0, $exitCode, "doctrine:migrations:migrate failed:\n$stdout\n$stderr");
    }

    /** @return list<array<string, mixed>> */
    private function fetchAll(string $dbPath, string $sql): array
    {
        $pdo = new \PDO('sqlite:'.$dbPath);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $rows = $pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
        $pdo = null;

        return array_values($rows);
    }

    private function quoteIdentifier(string $name): string
    {
        return '"'.str_replace('"', '""', $name).'"';
    }

    private function tempDbPath(string $label): string
    {
        $path = sys_get_temp_dir().\DIRECTORY_SEPARATOR.'ticketflow-f0-'.$label.'-'.bin2hex(random_bytes(4)).'.db';
        $this->tempFiles[] = $path;

        return $path;
    }
}
