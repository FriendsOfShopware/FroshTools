<?php

declare(strict_types=1);

namespace Frosh\Tools\Command;

use Frosh\Tools\Components\Environment\EnvironmentKeyValue;
use Frosh\Tools\Components\Environment\EnvironmentManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand('frosh:env:get', 'Get an environment variable')]
class EnvGetCommand extends Command
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/.env')]
        private readonly string $envPath
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('variable', InputArgument::OPTIONAL, 'Get specific environment variable');
        $this->addOption('key-value', null, InputOption::VALUE_NONE, 'Get value as key value');
        $this->addOption('json', null, InputOption::VALUE_NONE, 'Get value as json');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!is_file($this->envPath)) {
            throw new \RuntimeException(\sprintf('Cannot use this command as env file is missing at %s', $this->envPath));
        }

        $manager = new EnvironmentManager();
        $file = $manager->read($this->envPath);
        $mode = $input->getOption('json') ? 'json' : ($input->getOption('key-value') ? 'kv' : '');

        $variable = $input->getArgument('variable');

        if ($variable === null) {
            if ($mode === 'json') {
                $output->writeln(json_encode($file->values(), \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT));
            } elseif ($mode !== '' && $mode !== '0') {
                $output->writeln($file->__toString());
            }

            return self::SUCCESS;
        }

        if (!\is_string($variable)) {
            throw new \RuntimeException('Variable name must be a string');
        }

        $var = $file->get($variable);

        if (!$var instanceof EnvironmentKeyValue) {
            throw new \RuntimeException(sprintf('Cannot find variable with name: %s', $variable));
        }

        if ($mode === 'json') {
            $output->writeln(json_encode([$var->getKey() => $var->getValue()], \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT));
        } elseif ($mode === 'kv') {
            $output->writeln($var->getLine());
        } else {
            $output->writeln($var->getValue());
        }

        return self::SUCCESS;
    }
}
