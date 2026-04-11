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
        $result = $this->elasticsearchManager->deleteUnusedIndices();

        $deleted = $result['deleted'] ?? [];
        $errors = $result['errors'] ?? [];

        if ($deleted === []) {
            $output->writeln('No unused indices found.');
        } else {
            $output->writeln(\sprintf('Deleted %d unused index/indices:', \count($deleted)));
            foreach ($deleted as $index) {
                $output->writeln(' - ' . $index);
            }
        }

        if ($errors !== []) {
            $output->writeln(\sprintf('<error>Encountered %d error(s) while deleting:</error>', \count($errors)));
            foreach ($errors as $index => $message) {
                $output->writeln(\sprintf(' - %s: %s', $index, $message));
            }

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
