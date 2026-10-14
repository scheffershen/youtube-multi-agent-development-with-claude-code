<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Replays a realistic mix of API calls through the kernel (no web server needed)
 * so that var/log/dev.log fills with what real users would have triggered.
 *
 * The log-analyst agent reads that file. This command is the "production traffic"
 * stand-in for the demo.
 */
#[AsCommand(name: 'app:simulate-traffic', description: 'Replay a mix of good and bad API calls to populate the logs')]
final class SimulateTrafficCommand extends Command
{
    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('rounds', null, InputOption::VALUE_REQUIRED, 'How many times to replay the scenario', '1')
            ->addOption('reset', null, InputOption::VALUE_NONE, 'Drop and recreate the schema first, so the replay starts from an empty table');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($input->getOption('reset')) {
            $tool = new SchemaTool($this->em);
            $metadata = $this->em->getMetadataFactory()->getAllMetadata();
            $tool->dropSchema($metadata);
            $tool->createSchema($metadata);
            $output->writeln('Schema reset: ticket table is empty.');
        }

        $scenario = [
            ['GET', '/api/stats', null],
            ['POST', '/api/tickets', ['title' => 'Login page shows blank screen', 'description' => 'After the 2.3 deploy, Safari users see nothing.', 'priority' => 'high']],
            ['POST', '/api/tickets', ['title' => 'Typo on pricing page', 'description' => 'Recieve should be Receive.', 'priority' => 'low']],
            ['POST', '/api/tickets', ['title' => 'Export CSV is empty', 'description' => 'Exported file has headers only.']],
            ['POST', '/api/tickets', ['title' => 'Payment double charged', 'description' => 'Customer #4411 charged twice.', 'priority' => 'urgent']],
            ['GET', '/api/tickets', null],
            ['GET', '/api/tickets/1', null],
            ['GET', '/api/tickets/9999', null],
            ['PATCH', '/api/tickets/1/status', ['status' => 'in_progress']],
            ['PATCH', '/api/tickets/1/status', ['status' => 'done']],
            ['GET', '/api/tickets?status=open', null],
            ['GET', '/api/stats', null],
        ];

        $rounds = max(1, (int) $input->getOption('rounds'));
        $counts = [];

        for ($r = 0; $r < $rounds; ++$r) {
            foreach ($scenario as [$method, $uri, $body]) {
                $request = Request::create(
                    $uri,
                    $method,
                    server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
                    content: null === $body ? null : json_encode($body, JSON_THROW_ON_ERROR),
                );

                $response = $this->kernel->handle($request, HttpKernelInterface::MAIN_REQUEST, true);
                $this->kernel->terminate($request, $response);

                $status = $response->getStatusCode();
                $counts[$status] = ($counts[$status] ?? 0) + 1;
                $output->writeln(sprintf('%-6s %-28s -> %d', $method, $uri, $status));
            }
        }

        ksort($counts);
        $output->writeln('');
        foreach ($counts as $status => $n) {
            $output->writeln(sprintf('HTTP %d: %d', $status, $n));
        }
        $output->writeln('Logs written to var/log/dev.log');

        return Command::SUCCESS;
    }
}
