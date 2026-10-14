<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

// Give every PHPUnit process its own SQLite file (see DATABASE_URL in .env.test).
// Several agents run the suite at the same time; a shared file made full runs flaky.
if (!isset($_SERVER['TEST_TOKEN']) && !isset($_ENV['TEST_TOKEN']) && false === getenv('TEST_TOKEN')) {
    $_SERVER['TEST_TOKEN'] = $_ENV['TEST_TOKEN'] = '_'.getmypid();
    putenv('TEST_TOKEN=_'.getmypid());
}

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}

// Remove the per-process database when the run ends.
register_shutdown_function(static function (): void {
    $token = $_SERVER['TEST_TOKEN'] ?? '';
    if ('' !== $token && str_starts_with($token, '_')) {
        @unlink(dirname(__DIR__).'/var/data_test'.$token.'.db');
    }
});
