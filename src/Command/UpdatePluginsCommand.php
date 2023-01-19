<?php declare(strict_types=1);

namespace Frosh\Tools\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\KernelInterface;

#[AsCommand('frosh:plugin:update', 'Check for available plugin updates and install them')]
class UpdatePluginsCommand extends Command
{
    public function __construct(private readonly KernelInterface $kernel)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $pluginRefresh = new ArrayInput([
            'command' => 'plugin:refresh',
        ]);

        $pluginList = new ArrayInput([
            'command' => 'plugin:list',
            '--json' => true,
        ]);

        $runnerOutput = new BufferedOutput();

        $application = new Application($this->kernel);
        $application->setAutoExit(false);
        $application->run($pluginRefresh, new NullOutput());
        $application->run($pluginList, $runnerOutput);

        $plugins = \json_decode($runnerOutput->fetch(), true);

        $upgradablePlugins = [];

        foreach ($plugins as $plugin) {
            if ($plugin['upgradeVersion'] !== null) {
                $upgradablePlugins[] = $plugin['name'];
            }
        }

        $io = new SymfonyStyle($input, $output);

        if (count($upgradablePlugins) === 0) {
            $io->success('No updates available');

            return self::SUCCESS;
        }

        $pluginUpdate = new ArrayInput([
            'command' => 'plugin:update',
            'plugins' => $upgradablePlugins,
            '-n',
        ]);
        $application->run($pluginUpdate);

        return self::SUCCESS;
    }
}
