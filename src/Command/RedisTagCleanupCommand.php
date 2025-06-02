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
    name: 'frosh:redis-tag:cleanup',
    description: 'Cleanup orphaned Redis tags [Experimental]',
)]
class RedisTagCleanupCommand extends Command
{
    private const LUA_CLEANUP_SCRIPT = <<<'LUA'
        local deleted_keys = 0
        local removed_members = 0
        local processed_keys = 0

        -- Process each provided tag key
        for i, tag_key in ipairs(KEYS) do
            processed_keys = processed_keys + 1
            local members = redis.call('SMEMBERS', tag_key)
            local members_to_remove = {}

            -- Check each member if it exists
            for j, member in ipairs(members) do
                if redis.call('EXISTS', member) == 0 then
                    table.insert(members_to_remove, member)
                end
            end

            -- Remove non-existent members
            if #members_to_remove > 0 then
                redis.call('SREM', tag_key, unpack(members_to_remove))
                removed_members = removed_members + #members_to_remove
            end

            -- Delete empty sets
            if redis.call('SCARD', tag_key) == 0 then
                redis.call('DEL', tag_key)
                deleted_keys = deleted_keys + 1
            end
        end

        return {processed_keys, removed_members, deleted_keys}
    LUA;

    public function __construct(private readonly CacheRegistry $cacheRegistry)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be cleaned without actually cleaning');
        $this->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Number of keys to process in each batch', '100');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $cacheAdapter = $this->cacheRegistry->get('cache.object');
        $dryRun = $input->getOption('dry-run');
        $batchSize = (int) $input->getOption('batch-size');

        $redis = $cacheAdapter->getRedisOrFail();

        $activeNamespace = $cacheAdapter->getNamespace();
        $pattern = $activeNamespace . ":\x01tags\x01*";

        $io->title('Redis Tag Cleanup');
        $io->writeln(\sprintf('Active namespace: <info>%s</info>', $activeNamespace));
        $io->writeln(\sprintf('Tag pattern: <info>%s</info>', str_replace("\x01", '\\x01', $pattern)));

        if ($dryRun) {
            $io->note('Running in dry-run mode - no tags will be cleaned');

            return $this->performDryRun($redis, $pattern, $io);
        }

        // Load the Lua script
        $scriptSha = $redis->script('LOAD', self::LUA_CLEANUP_SCRIPT);

        $totalProcessed = 0;
        $totalRemoved = 0;
        $totalDeleted = 0;

        // Use SCAN to iterate through tag keys
        $iterator = null;
        $io->progressStart();

        do {
            $keys = $redis->scan($iterator, $pattern, $batchSize);
            if ($keys === false) {
                break;
            }

            if (!empty($keys)) {
                $io->writeln(\sprintf("\nProcessing batch of %d tag keys...", \count($keys)));

                try {
                    // Execute Lua script for this batch
                    $result = $redis->evalSha($scriptSha, $keys, \count($keys));

                    if (\is_array($result) && \count($result) === 3) {
                        [$processed, $removed, $deleted] = $result;

                        $totalProcessed += $processed;
                        $totalRemoved += $removed;
                        $totalDeleted += $deleted;

                        $io->writeln(\sprintf(
                            '  Processed: %d, Members removed: %d, Keys deleted: %d',
                            $processed,
                            $removed,
                            $deleted
                        ));
                    }
                } catch (\Exception $e) {
                    $io->error(\sprintf('Error processing batch: %s', $e->getMessage()));
                }

                $io->progressAdvance(\count($keys));
            }
        } while ($iterator > 0);

        $io->progressFinish();

        $io->section('Cleanup Summary');
        $io->table(
            ['Metric', 'Count'],
            [
                ['Total keys processed', $totalProcessed],
                ['Total members removed', $totalRemoved],
                ['Total keys deleted', $totalDeleted],
            ]
        );

        $io->success('Tag cleanup completed successfully');

        return Command::SUCCESS;
    }

    private function performDryRun(\Redis $redis, string $pattern, SymfonyStyle $io): int
    {
        $totalTagKeys = 0;
        $orphanedMembers = 0;
        $emptyTags = 0;
        $sampleOrphans = [];

        // Use SCAN to iterate through tag keys
        $iterator = null;
        $io->progressStart();

        do {
            $keys = $redis->scan($iterator, $pattern, 100);
            if ($keys === false) {
                break;
            }

            foreach ($keys as $tagKey) {
                ++$totalTagKeys;
                /** @var array $members */
                $members = $redis->sMembers($tagKey);
                $orphanedInThisTag = 0;

                foreach ($members as $member) {
                    if (!$redis->exists($member)) {
                        ++$orphanedMembers;
                        ++$orphanedInThisTag;

                        // Collect some examples
                        if (\count($sampleOrphans) < 10) {
                            $sampleOrphans[] = [
                                'tag' => $tagKey,
                                'member' => $member,
                            ];
                        }
                    }
                }

                // Check if all members are orphaned (tag would be deleted)
                if ($orphanedInThisTag === \count($members)) {
                    ++$emptyTags;
                }

                $io->progressAdvance();
            }
        } while ($iterator > 0);

        $io->progressFinish();

        $io->section('Dry Run Summary');
        $io->table(
            ['Metric', 'Count'],
            [
                ['Total tag keys found', $totalTagKeys],
                ['Orphaned members to remove', $orphanedMembers],
                ['Empty tags to delete', $emptyTags],
            ]
        );

        if (!empty($sampleOrphans)) {
            $io->section('Sample Orphaned Members (first 10)');
            $tableData = [];
            foreach ($sampleOrphans as $orphan) {
                $tableData[] = [
                    substr($orphan['tag'], 0, 50) . (\strlen($orphan['tag']) > 50 ? '...' : ''),
                    substr($orphan['member'], 0, 50) . (\strlen($orphan['member']) > 50 ? '...' : ''),
                ];
            }
            $io->table(['Tag Key', 'Orphaned Member'], $tableData);
        }

        $io->warning(\sprintf(
            'Dry run complete - would have removed %d orphaned members and deleted %d empty tags',
            $orphanedMembers,
            $emptyTags
        ));

        return Command::SUCCESS;
    }
}
