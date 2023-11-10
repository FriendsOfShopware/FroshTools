<?php

declare(strict_types=1);

namespace Frosh\Tools\Command;

use Frosh\Tools\Components\Environment\EnvironmentManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand('frosh:env:set', 'Set an environment variable')]
class EnvSetCommand extends Command
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/.env')]
        private readonly string $envPath
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('variable', InputArgument::REQUIRED, 'Variable name');
        $this->addArgument('value', InputArgument::REQUIRED, 'Variable value');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $manager = new EnvironmentManager();
        $file = $manager->read($this->envPath);
        $variable = $input->getArgument('variable');
        if (!\is_string($variable)) {
            throw new \RuntimeException('Variable name must be a string');
        }

        $value = $input->getArgument('value');
        if (!\is_string($value)) {
            throw new \RuntimeException('Variable value must be a string');
        }

        $file->set($variable, $value);
        $manager->save($this->envPath, $file);

        $io = new SymfonyStyle($input, $output);
        $io->success('Saved .env file');

        return self::SUCCESS;
    }
}
