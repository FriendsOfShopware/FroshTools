<?php

declare(strict_types=1);

namespace Frosh\Tools\Command;

use Frosh\Tools\Components\CacheRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'frosh:redis-namespace:list',
    description: 'List all Redis namespaces [Experimental]',
)]
class RedisNamespaceListCommand extends Command
{
    public function __construct(private readonly CacheRegistry $cacheRegistry)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $cacheAdapter = $this->cacheRegistry->get('cache.object');

        try {
            $redis = $cacheAdapter->getRedisOrFail();
        } catch (\RuntimeException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $activeNamespaces = $this->cacheRegistry->getActiveNamespaces();
        $io->title('Redis Key Groupping by Namespace');

        // Group keys by first 10 characters
        $keyGroups = [];
        $totalKeys = 0;

        // Use SCAN to iterate through all keys efficiently
        $iterator = null;
        do {
            $keys = $redis->scan($iterator, null, 1000);
            if ($keys === false) {
                break;
            }

            foreach ($keys as $key) {
                ++$totalKeys;
                $prefix = substr($key, 0, 10);
                if (!isset($keyGroups[$prefix])) {
                    $keyGroups[$prefix] = 0;
                }
                ++$keyGroups[$prefix];
            }
        } while ($iterator > 0);

        // Sort by count descending
        arsort($keyGroups);

        // Display results in a table
        $tableData = [];
        foreach ($keyGroups as $prefix => $count) {
            $tableData[] = [$prefix, $count, \sprintf('%.1f%%', ($count / $totalKeys) * 100), \in_array($prefix, $activeNamespaces, true) ? 'Yes' : 'No'];
        }

        usort($tableData, function ($a, $b) {
            return $b[0] <=> $a[0];
        });

        $io->table(
            ['Prefix', 'Count', 'Percentage', 'Active'],
            $tableData
        );

        $io->success(\sprintf('Total keys analyzed: %d', $totalKeys));

        return Command::SUCCESS;
    }
}
