<?php declare(strict_types=1);

namespace Frosh\Tools\Command;

use Frosh\Tools\Components\ExtensionChecksum\ExtensionFileHashService;
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
    name: 'frosh:extension:checksum:check',
    description: 'Checks the integrity of extension files',
)]
class ExtensionChecksumCheckCommand extends Command
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

        $extensions = $this->getExtension((string) $input->getArgument('extension'), $io);
        if ($extensions->count() < 1) {
            $io->error('No extensions found');

            return self::FAILURE;
        }

        $io->info(\sprintf('Found %d extensions to check', $extensions->count()));

        $success = true;
        foreach ($extensions as $extension) {
            $io->info('Checking extension: ' . $extension->getName());

            $extensionChecksumCheckResult = $this->extensionFileHashService->checkExtensionForChanges($extension);
            if ($extensionChecksumCheckResult->isFileMissing()) {
                $io->warning(\sprintf('Checksum file for extension "%s" not found - integrity check skipped', $extension->getName()));

                // Not setting $success to false because the creation of the checksum file is optional
                continue;
            }

            // If the checksum file format changes: Add a check for $extensionChecksumResult->isWrongVersion() here
            // Right now the version is always 1.0.0

            if ($extensionChecksumCheckResult->isWrongExtensionVersion()) {
                $io->error(\sprintf('Checksum file for extension "%s" was generated for a different extension version', $extension->getName()));
                $success = false;
                continue;
            }

            if ($extensionChecksumCheckResult->isExtensionOk()) {
                $io->success(\sprintf('Extension "%s" has no detected file-changes.', $extension->getName()));

                continue;
            }

            $success = false;

            $io->error(\sprintf('Extension "%s" has changed code.', $extension->getName()));
            $this->outputFileChanges($io, 'New files detected:', $extensionChecksumCheckResult->getNewFiles());
            $this->outputFileChanges($io, 'Changed files detected:', $extensionChecksumCheckResult->getChangedFiles());
            $this->outputFileChanges($io, 'Missing files detected:', $extensionChecksumCheckResult->getMissingFiles());
        }

        return $success ? self::SUCCESS : self::FAILURE;
    }

    private function getExtension(string $name, ShopwareStyle $io): PluginCollection
    {
        // @phpstan-ignore-next-line
        $context = method_exists(Context::class, 'createCLIContext') ? Context::createCLIContext() : Context::createDefaultContext();

        if (!$name) {
            $io->info('Checking all extensions');

            /** @var PluginCollection $extensions */
            $extensions = $this->pluginRepository->search(new Criteria(), $context)->getEntities();

            return $extensions;
        }

        $extensions = new PluginCollection();

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $name));
        $extension = $this->pluginRepository->search($criteria, $context)->first();
        if ($extension instanceof PluginEntity) {
            $extensions->add($extension);
        } else {
            $io->error(\sprintf('Extension "%s" not found', $name));
        }

        return $extensions;
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
