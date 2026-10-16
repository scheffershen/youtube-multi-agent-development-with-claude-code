<?php
/**
 * Read-only row counts for "no write survives the test run" criteria (verifier, recorder).
 *
 * Usage (from the project root, with the project's PHP):
 *   php .claude/scripts/count-rows.php 'App\Entity\Ticket' 'App\Entity\Agent' > reports/items/<id>/counts-before.txt
 *   ... run the tests ...
 *   php .claude/scripts/count-rows.php 'App\Entity\Ticket' 'App\Entity\Agent' > reports/items/<id>/counts-after.txt
 *   diff reports/items/<id>/counts-before.txt reports/items/<id>/counts-after.txt
 *
 * It counts the database PHPUnit uses, not the one the shell happens to point at: the
 * APP_ENV and DATABASE_URL that phpunit.xml.dist (or phpunit.xml) forces are forced here the
 * same way before anything boots. The .env files alone are not enough, because .env usually
 * sets APP_ENV=dev and wins over Dotenv's default. The first two lines name the environment
 * and the database, so a reader can see which one was measured; the URL is never printed.
 * Only count() is called; nothing is written. Exit code 1 if any class could not be counted
 * (missing table, unknown entity): a count that failed is not evidence.
 */
declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

$root = dirname(__DIR__, 2);
$classes = array_slice($argv, 1);
if ($classes === []) {
    fwrite(STDERR, "usage: php .claude/scripts/count-rows.php <EntityClass> [<EntityClass> ...]\n");
    exit(2);
}

$config = file_exists($root.'/phpunit.xml') ? $root.'/phpunit.xml' : $root.'/phpunit.xml.dist';
$forced = [];
if (file_exists($config)) {
    $xml = simplexml_load_file($config);
    foreach (['server', 'env'] as $kind) {
        foreach ($xml->php->{$kind} ?? [] as $var) {
            $forced[(string) $var['name']] = (string) $var['value'];
        }
    }
}
$forced['APP_ENV'] = $forced['APP_ENV'] ?? 'test';
foreach (['APP_ENV', 'DATABASE_URL'] as $name) {
    if (isset($forced[$name])) {
        putenv("$name={$forced[$name]}");
        $_SERVER[$name] = $_ENV[$name] = $forced[$name];
    }
}

require $root.'/vendor/autoload.php';
if (file_exists($root.'/.env')) {
    (new Dotenv())->bootEnv($root.'/.env', $forced['APP_ENV']);
}

$kernelClass = 'App\Kernel';
$kernel = new $kernelClass($_SERVER['APP_ENV'], false);
$kernel->boot();
$em = $kernel->getContainer()->get('doctrine')->getManager();
$conn = $em->getConnection();
$params = $conn->getParams();
echo 'env: ', $kernel->getEnvironment(), "\n";
echo 'database: ', $params['dbname'] ?? $params['path'] ?? $conn->getDatabase() ?? '(unknown)', "\n";
$failed = 0;
foreach ($classes as $class) {
    try {
        echo $class, ' ', $em->getRepository($class)->count([]), "\n";
    } catch (\Throwable $e) {
        ++$failed;
        echo $class, ' ERROR: ', strtok($e->getMessage(), "\n"), "\n";
    }
}
exit($failed > 0 ? 1 : 0);
