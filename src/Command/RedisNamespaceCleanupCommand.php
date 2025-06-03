<?php

declare(strict_types=1);

namespace Frosh\Tools\Command;

use Frosh\Tools\Components\CacheRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'frosh:redis-namespace:cleanup',
    description: 'Delete all Redis namespaces except the active one [Experimental]',
)]
class RedisNamespaceCleanupCommand extends Command
{
    public function __construct(private readonly CacheRegistry $cacheRegistry)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be deleted without actually deleting');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $cacheAdapter = $this->cacheRegistry->get('cache.object');
        $dryRun = $input->getOption('dry-run');

        try {
            $redis = $cacheAdapter->getRedisOrFail();
        } catch (\RuntimeException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $activeNamespace = $cacheAdapter->getNamespace();
        $io->title('Redis Namespace Cleanup');
        $io->writeln(\sprintf('Active namespace: <info>%s</info>', $activeNamespace));

        if ($dryRun) {
            $io->note('Running in dry-run mode - no keys will be deleted');
        }

        // Group keys by namespace (first 10 characters)
        $namespaces = [];
        $totalKeys = 0;
        $keysToDelete = [];

        // Use SCAN to iterate through all keys efficiently
        $iterator = null;
        do {
            $keys = $redis->scan($iterator, null, 1000);
            if ($keys === false) {
                break;
            }

            foreach ($keys as $key) {
                ++$totalKeys;
                $namespace = substr($key, 0, 10);

                if (!isset($namespaces[$namespace])) {
                    $namespaces[$namespace] = 0;
                }
                ++$namespaces[$namespace];

                // Track keys that are not in the active namespace
                if ($namespace !== $activeNamespace) {
                    $keysToDelete[] = $key;
                }
            }
        } while ($iterator > 0);

        // Display namespace summary
        $tableData = [];
        foreach ($namespaces as $namespace => $count) {
            $status = $namespace === $activeNamespace ? 'KEEP' : 'DELETE';
            $tableData[] = [$namespace, $count, $status];
        }

        $io->section('Namespace Summary');
        $io->table(
            ['Namespace', 'Key Count', 'Action'],
            $tableData
        );

        $deleteCount = \count($keysToDelete);

        if ($deleteCount === 0) {
            $io->success('No keys to delete - only the active namespace exists');

            return Command::SUCCESS;
        }

        $io->writeln(\sprintf('Keys to delete: <comment>%d</comment> out of <comment>%d</comment> total keys', $deleteCount, $totalKeys));

        if (!$dryRun) {
            if (!$io->confirm('Do you want to proceed with deleting these keys?')) {
                $io->warning('Operation cancelled');

                return Command::SUCCESS;
            }

            $io->progressStart($deleteCount);

            // Delete keys in batches for better performance
            $batchSize = 1000;
            for ($i = 0; $i < $deleteCount; $i += $batchSize) {
                $batch = \array_slice($keysToDelete, $i, $batchSize);
                $redis->del(...$batch);
                $io->progressAdvance(\count($batch));
            }

            $io->progressFinish();
            $io->success(\sprintf('Successfully deleted %d keys from inactive namespaces', $deleteCount));
        } else {
            $io->warning(\sprintf('Dry run complete - would have deleted %d keys', $deleteCount));
        }

        return Command::SUCCESS;
    }
}
