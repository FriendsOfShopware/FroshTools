<?php declare(strict_types=1);

namespace Frosh\Tools\Command;

use Composer\Console\Application;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\KernelPluginLoader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class UpdateComposerPluginsCommand extends Command
{
    public static $defaultName = 'frosh:composer-plugin:update';
    public static $defaultDescription = 'Check for available plugin updates and install them';
    private Application $application;

    private string $projectDir;
    private KernelPluginLoader $pluginLoader;

    public function __construct(string $projectDir, KernelPluginLoader $pluginLoader)
    {
        parent::__construct();
        $this->application = new Application();
        $this->application->setAutoExit(false);

        $this->projectDir = $projectDir;
        $this->pluginLoader = $pluginLoader;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $plugins = $this->pluginLoader->getPluginInfos();

        $composerNames = [];
        foreach ($plugins as $plugin) {
            if ($plugin['managedByComposer'] === true) {
                $composerNames[] = $plugin['composerName'];
            }
        }

        $composerOutput = new BufferedOutput();
        $composerinput = new ArrayInput(
            [
                'command' => 'outdated',
                '--working-dir' => $this->projectDir,
                '--direct' => null,
                '--format' => 'json',
            ]
        );

        $this->application->run($composerinput, $composerOutput);

        $updates = [];
        $packages = \json_decode($composerOutput->fetch(), true);

        if (!isset($packages['installed'])) {
            $io->error('No installed composer packages found!');

            return self::FAILURE;
        }

        foreach ($packages['installed'] as $package) {
            if (!\in_array($package['name'], $composerNames, true)) {
                continue;
            }

            $updates[] = $package['name'];
        }

        $missedUpdates = [];
        if (count($updates) === 0) {
            $io->success('No updates available');

            return self::SUCCESS;
        }

        $composerUpdate = new ArrayInput(
            [
                'command' => 'update',
                '--working-dir' => $this->projectDir,
                'packages' => $updates,
            ]
        );
        $this->application->run($composerUpdate, $composerOutput);

        $updateOutput = $composerOutput->fetch();
        foreach ($updates as $update) {
            if (!str_contains($updateOutput, $update)) {
                $missedUpdates[] = $update;
            }
        }

        $updates = \array_diff($updates, $missedUpdates);

        if (\count($updates) > 0) {
            $io->comment('Plugins updated: ' . \implode(', ', $updates));
        }

        if (\count($missedUpdates) > 0) {
            $io->warning('These packages have not been updated. You should check the version constraint in composer.json: ' . \implode(', ', $missedUpdates));
        }

        $io->success('Finished!');

        return self::SUCCESS;
    }
}
