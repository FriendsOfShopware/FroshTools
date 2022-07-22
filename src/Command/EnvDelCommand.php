<?php declare(strict_types=1);

namespace Frosh\Tools\Command;

use Frosh\Tools\Components\Environment\EnvironmentManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class EnvDelCommand extends Command
{
    public static $defaultName = 'frosh:env:Del';
    public static $defaultDescription = 'Delete environment variable';
    private string $envPath;

    public function __construct(string $envPath)
    {
        parent::__construct();
        $this->envPath = $envPath;
    }

    public function configure(): void
    {
        $this->addArgument('variable', InputArgument::REQUIRED, 'Variable name');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $manager = new EnvironmentManager();
        $file = $manager->read($this->envPath);

        $file->delete($input->getArgument('variable'));
        $manager->save($this->envPath, $file);

        $io = new SymfonyStyle($input, $output);
        $io->success('Saved .env file');

        return self::SUCCESS;
    }
}
