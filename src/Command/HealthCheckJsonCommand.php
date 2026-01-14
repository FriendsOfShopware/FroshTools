<?php

declare(strict_types=1);

namespace Frosh\Tools\Command;

use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

#[AsCommand('frosh-tools:health-check-json', 'Returns a JSON with all health check checkers result merged like with /health/status route')]
class HealthCheckJsonCommand extends Command
{
    /**
     * @param CheckerInterface[] $healthCheckers
     */
    public function __construct(
        #[AutowireIterator('frosh_tools.health_checker')]
        private readonly iterable $healthCheckers,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $collection = new HealthCollection();
        foreach ($this->healthCheckers as $checker) {
            $checker->collect($collection);
        }

        $output->writeln(json_encode($collection, \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR));

        return Command::SUCCESS;
    }
}
