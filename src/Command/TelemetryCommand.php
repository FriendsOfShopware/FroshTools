<?php

declare(strict_types=1);

namespace Frosh\Tools\Command;

use Frosh\Tools\Components\Telemetry\TelemetryService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('frosh:telemetry', 'Preview the anonymous telemetry payload, or send it now with --send (opt-in required)')]
class TelemetryCommand extends Command
{
    public function __construct(private readonly TelemetryService $telemetryService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('send', null, InputOption::VALUE_NONE, 'Send the event to PostHog EU now (only when telemetry is enabled in the plugin configuration)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->writeln(json_encode($this->telemetryService->preview(), \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR));

        if ($input->getOption('send') !== true) {
            $io->note('Preview only, nothing was sent. Use --send to send now (requires the telemetry opt-in in the plugin configuration).');

            return Command::SUCCESS;
        }

        if (!$this->telemetryService->isEnabled()) {
            $io->warning('Telemetry is not enabled in the plugin configuration (FroshTools.config.telemetryEnabled). Nothing was sent.');

            return Command::FAILURE;
        }

        if (!$this->telemetryService->send()) {
            $io->error('Sending failed, see the frosh-tools log channel for details.');

            return Command::FAILURE;
        }

        $io->success('Telemetry event sent to PostHog EU.');

        return Command::SUCCESS;
    }
}
