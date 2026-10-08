<?php

declare(strict_types=1);

namespace Frosh\Tools\Command;

use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\DataIntegrityCollection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

#[AsCommand('frosh-tools:data-integrity-check-json', 'Returns a JSON with all data integrity check results like the /data-integrity/status route')]
class DataIntegrityCheckJsonCommand extends Command
{
    /**
     * @param CheckerInterface[] $dataIntegrityCheckers
     * @param array<string> $ignoredChecks
     */
    public function __construct(
        #[AutowireIterator('frosh_tools.data_integrity_checker')]
        private readonly iterable $dataIntegrityCheckers,
        #[Autowire(param: 'frosh_tools.checker.disabled_checks')]
        private readonly array $ignoredChecks,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $collection = new DataIntegrityCollection();
        foreach ($this->dataIntegrityCheckers as $checker) {
            $checker->collect($collection);
        }

        $collection->sortByState();
        $collection->removeByIds($this->ignoredChecks);

        $output->writeln(json_encode($collection, \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR));

        return Command::SUCCESS;
    }
}
