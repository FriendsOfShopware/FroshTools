<?php

declare(strict_types=1);

namespace Frosh\Tools\Command;

use Frosh\Tools\Components\DatabaseDiff\DatabaseIntrospectionService;
use Shopware\Core\Framework\Util\Json;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand('frosh:database:dump-introspection')]
class DumpDatabaseIntrospection extends Command
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/var')]
        private readonly string $envPath,
        private readonly DatabaseIntrospectionService $databaseIntrospectionService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io     = new SymfonyStyle($input, $output);
        $schema = $this->databaseIntrospectionService->getDatabaseSchema();
        $path   = $this->envPath . '/swdb.schema.json';

        $io->info(\sprintf('Dumping database introspection for Shopware %s...', $schema->version));

        if (!\file_put_contents($path, Json::encode($schema), \LOCK_EX)) {
            $io->error('Failed to write database schema.');

            return Command::FAILURE;
        }

        $io->success(\sprintf('Database schema written to "%s".', $path));

        return Command::SUCCESS;
    }
}
