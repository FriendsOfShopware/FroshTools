<?php

declare(strict_types=1);

namespace Frosh\Tools\Command;

use Frosh\Tools\Components\Elasticsearch\ElasticsearchManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('frosh:es:delete-unused-indices', 'Deletes unused Elasticsearch indices')]
class DeleteUnusedIndicesCommand extends Command
{
    public function __construct(
        private readonly ElasticsearchManager $elasticsearchManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->elasticsearchManager->deleteUnusedIndices();

        return Command::SUCCESS;
    }
}