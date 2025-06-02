<?php declare(strict_types=1);

namespace Frosh\Tools\Command;

use Frosh\Tools\Components\ExtensionChecksum\ExtensionFileHashService;
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
    name: 'frosh:extension:checksum:create',
    description: 'Creates a list of files and their checksums for an extension',
)]
class ExtensionChecksumCreateCommand extends Command
{
    /**
     * @param EntityRepository<PluginCollection> $pluginRepository
     */
    public function __construct(
        private readonly EntityRepository $pluginRepository,
        private readonly ExtensionFileHashService $extensionFileHashService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('extension', InputArgument::OPTIONAL, 'Extension name');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new ShopwareStyle($input, $output);
        // @phpstan-ignore-next-line
        $context = method_exists(Context::class, 'createCLIContext') ? Context::createCLIContext() : Context::createDefaultContext();

        $extensionName = (string) $input->getArgument('extension');
        $extension = $this->getExtension($extensionName, $context);

        if (!$extension instanceof PluginEntity) {
            $io->error(\sprintf('Extension "%s" not found', $extensionName));

            return self::FAILURE;
        }

        $checksumFilePath = $this->extensionFileHashService->getChecksumFilePathForExtension($extension);
        if (!$checksumFilePath) {
            $io->error(\sprintf('Extension "%s" checksum file path could not be identified', $extension->getName()));

            return self::FAILURE;
        }

        $checksumStruct = $this->extensionFileHashService->getChecksumData($extension);

        $io->info(\sprintf('Writing %s checksums for extension "%s" to file %s', \count($checksumStruct->getHashes()), $extension->getName(), $checksumFilePath));

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

    private function getExtension(string $name, Context $context): ?Entity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $name));

        return $this->pluginRepository->search($criteria, $context)->first();
    }
}
