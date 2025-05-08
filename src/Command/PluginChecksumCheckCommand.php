<?php declare(strict_types=1);

namespace Frosh\Tools\Command;

use Frosh\Tools\Components\PluginChecksum\PluginFileHashService;
use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\PluginCollection;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'frosh:plugin:checksum:check',
    description: 'Check the integrity of plugin files',
)]
class PluginChecksumCheckCommand extends Command
{
    /**
     * @param EntityRepository<PluginCollection> $pluginRepository
     */
    public function __construct(
        private readonly EntityRepository $pluginRepository,
        private readonly PluginFileHashService $pluginFileHashService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('plugin', InputArgument::OPTIONAL, 'Plugin name');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new ShopwareStyle($input, $output);

        $plugins = $this->getPlugins((string) $input->getArgument('plugin'), $io);
        if ($plugins->count() < 1) {
            $io->error('No plugins found');

            return self::FAILURE;
        }

        $io->info(\sprintf('Found %d plugins to check', $plugins->count()));

        $success = true;
        foreach ($plugins as $plugin) {
            $io->info('Checking plugin: ' . $plugin->getName());

            $pluginChecksumCheckResult = $this->pluginFileHashService->checkPluginForChanges($plugin);
            if ($pluginChecksumCheckResult->isFileMissing()) {
                $io->warning(\sprintf('Checksum file for plugin "%s" not found - integrity check skipped', $plugin->getName()));

                // Not setting $success to false because the creation of the checksum file is optional
                continue;
            }

            if ($pluginChecksumCheckResult->isWrongVersion()) {
                $io->error(\sprintf('Checksum file for plugin "%s" was generated for different version', $plugin->getName()));
                $success = false;
                continue;
            }

            if ($pluginChecksumCheckResult->getNewFiles() === []
                && $pluginChecksumCheckResult->getChangedFiles() === []
                && $pluginChecksumCheckResult->getMissingFiles() === []
            ) {
                $io->success(\sprintf('Plugin "%s" has no detected file-changes.', $plugin->getName()));

                continue;
            }

            $success = false;

            $io->error(\sprintf('Plugin "%s" has changed code.', $plugin->getName()));
            $this->outputFileChanges($io, 'New files detected:', $pluginChecksumCheckResult->getNewFiles());
            $this->outputFileChanges($io, 'Changed files detected:', $pluginChecksumCheckResult->getChangedFiles());
            $this->outputFileChanges($io, 'Missing files detected:', $pluginChecksumCheckResult->getMissingFiles());
        }

        return $success ? self::SUCCESS : self::FAILURE;
    }

    private function getPlugins(string $pluginName, ShopwareStyle $io): PluginCollection
    {
        $context = Context::createDefaultContext();

        if (!$pluginName) {
            $io->info('Checking all plugins');

            /** @var PluginCollection $plugins */
            $plugins = $this->pluginRepository->search(new Criteria(), $context)->getEntities();

            return $plugins;
        }

        $plugins = new PluginCollection();

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $pluginName));
        $plugin = $this->pluginRepository->search($criteria, $context)->first();
        if ($plugin instanceof PluginEntity) {
            $plugins->add($plugin);
        }

        return $plugins;
    }

    /**
     * @param string[] $files
     */
    private function outputFileChanges(ShopwareStyle $io, string $text, array $files): void
    {
        if ($files) {
            $io->warning($text);
            $io->listing($files);
        }
    }
}
