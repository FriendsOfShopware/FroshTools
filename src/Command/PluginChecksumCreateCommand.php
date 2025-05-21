<?php declare(strict_types=1);

namespace Frosh\Tools\Command;

use Frosh\Tools\Components\PluginChecksum\PluginFileHashService;
use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
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
    name: 'frosh:plugin:checksum:create',
    description: 'Creates a list of files and their checksums for a plugin',
)]
class PluginChecksumCreateCommand extends Command
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
        $this->addArgument('plugin', InputArgument::REQUIRED, 'Plugin name');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new ShopwareStyle($input, $output);
        // @phpstan-ignore-next-line
        $context = method_exists(Context::class, 'createCLIContext') ? Context::createCLIContext() : Context::createDefaultContext();

        $pluginName = (string) $input->getArgument('plugin');
        $plugin = $this->getPlugin($pluginName, $context);

        if (!$plugin instanceof PluginEntity) {
            $io->error(\sprintf('Plugin "%s" not found', $pluginName));

            return self::FAILURE;
        }

        $checksumFilePath = $this->pluginFileHashService->getChecksumFilePathForPlugin($plugin);
        if (!$checksumFilePath) {
            $io->error(\sprintf('Plugin "%s" checksum file path could not be identified', $plugin->getName()));

            return self::FAILURE;
        }

        $checksumStruct = $this->pluginFileHashService->getChecksumData($plugin);

        $io->info(\sprintf('Writing %s checksums for plugin "%s" to file %s', \count($checksumStruct->getHashes()), $plugin->getName(), $checksumFilePath));

        $directory = \dirname($checksumFilePath);
        if (!is_dir($directory)) {
            $io->error(\sprintf('Directory "%s" does not exist or cannot be read', $directory));

            return self::FAILURE;
        }

        if (!is_writable($directory)) {
            $io->error(\sprintf('Directory "%s" is not writable', $directory));

            return self::FAILURE;
        }

        if (file_put_contents($checksumFilePath, \json_encode($checksumStruct->jsonSerialize(), \JSON_THROW_ON_ERROR)) === false) {
            $io->error(\sprintf('Failed to write to file "%s"', $checksumFilePath));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function getPlugin(string $pluginName, Context $context): ?Entity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $pluginName));

        return $this->pluginRepository->search($criteria, $context)->first();
    }
}
