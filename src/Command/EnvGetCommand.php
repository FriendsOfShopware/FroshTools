<?php declare(strict_types=1);

namespace Frosh\Tools\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use RuntimeException;
use Frosh\Tools\Components\Environment\EnvironmentManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('frosh:env:get', 'Get an environment variable')]
class EnvGetCommand extends Command
{
    public function __construct(private readonly string $envPath)
    {
        parent::__construct();
    }

    public function configure(): void
    {
        $this->addArgument('variable', InputArgument::OPTIONAL, 'Get specific environment variable');
        $this->addOption('key-value', null, InputOption::VALUE_NONE, 'Get value as key value');
        $this->addOption('json', null, InputOption::VALUE_NONE, 'Get value as json');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!is_file($this->envPath)) {
            throw new RuntimeException(\sprintf('Cannot use this command as env file is missing at %s', $this->envPath));
        }

        $manager = new EnvironmentManager();
        $file = $manager->read($this->envPath);
        $mode = $input->getOption('json') ? 'json' : ($input->getOption('key-value') ? 'kv' : '');

        if ($input->getArgument('variable') === null) {
            if ($mode === 'json') {
                $output->writeln(json_encode($file->values(), \JSON_PRETTY_PRINT));
            } elseif ($mode) {
                $output->writeln($file->__toString());
            }

            return self::SUCCESS;
        }

        $var = $file->get($input->getArgument('variable'));

        if ($var === null) {
            throw new RuntimeException(sprintf('Cannot find variable with name: %s', $input->getArgument('variable')));
        }

        if ($mode === 'json') {
            $output->writeln(json_encode([$var->getKey() => $var->getValue()], \JSON_PRETTY_PRINT));
        } elseif ($mode === 'kv') {
            $output->writeln($var->getLine());
        } else {
            $output->writeln($var->getValue());
        }

        return self::SUCCESS;
    }
}
