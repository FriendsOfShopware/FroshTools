<?php

declare(strict_types=1);

namespace Frosh\Tools\Command;

use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\PluginCollection;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Dumper\YamlDumper;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\Finder\Finder;

#[AsCommand(
    name: 'frosh:extension:convert-services-xml',
    description: 'Converts a extension\'s services.xml files to services.yaml. "bin/console plugin:refresh" needs to be run before executing this command. If you use dynamic service declarations (i.e. "autowire"), you need to adjust your services.xml manually. This command saves the resolved declarations',
)]
class ExtensionServicesXmlConvertCommand extends Command
{
    /**
     * @param EntityRepository<PluginCollection> $pluginRepository
     */
    public function __construct(
        private readonly EntityRepository $pluginRepository,
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('plugin', InputArgument::REQUIRED, 'Plugin name');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Only output generated yaml, do not adjust files');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new ShopwareStyle($input, $output);

        $name = (string) $input->getArgument('plugin');
        $dryRun = (bool) $input->getOption('dry-run');

        // Cross version support
        // @phpstan-ignore-next-line
        $context = method_exists(Context::class, 'createCLIContext') ? Context::createCLIContext() : Context::createDefaultContext();
        $plugin = $this->findPlugin($name, $context);
        if (!$plugin instanceof PluginEntity) {
            $io->error(\sprintf('Plugin "%s" not found', $name));

            return self::FAILURE;
        }

        $relativePath = $plugin->getPath();
        if ($relativePath === null || $relativePath === '') {
            $io->error(\sprintf('Plugin "%s" has no path', $name));

            return self::FAILURE;
        }

        $pluginPath = \rtrim($this->projectDir . '/' . $relativePath, '/\\');
        if (!\is_dir($pluginPath)) {
            $io->error(\sprintf('Plugin path "%s" does not exist', $pluginPath));

            return self::FAILURE;
        }

        $xmlFinder = (new Finder())->in($pluginPath)->path('#Resources/config(/|$)#')->files()->name('services.xml');
        if (!$xmlFinder->hasResults()) {
            $io->info(\sprintf('No services.xml files found under %s', $pluginPath));

            return self::SUCCESS;
        }

        $filesystem = new Filesystem(new LocalFilesystemAdapter($pluginPath));

        foreach ($xmlFinder as $file) {
            $xmlPath = $file->getRealPath();
            if ($xmlPath === false) {
                continue;
            }

            /** @var string $yamlPath */
            $yamlPath = preg_replace('/\.xml$/', '.yaml', $xmlPath);

            $relativeXml = \ltrim(\substr($xmlPath, \strlen($pluginPath)), '/\\');
            $relativeYaml = \ltrim(\substr($yamlPath, \strlen($pluginPath)), '/\\');

            if ($filesystem->fileExists($relativeYaml)) {
                $io->warning(\sprintf('Skipping %s: %s already exists', $xmlPath, $yamlPath));

                continue;
            }

            $yamlString = $this->generateYamlString($xmlPath);
            if ($dryRun) {
                $io->info($yamlPath);
                $io->writeln($yamlString);

                continue;
            }

            $filesystem->write($relativeYaml, $yamlString);
            $filesystem->delete($relativeXml);

            $io->success(\sprintf('Converted %s -> %s', $xmlPath, $yamlPath));
        }

        return self::SUCCESS;
    }

    private function findPlugin(string $name, Context $context): ?PluginEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $name));
        $criteria->setLimit(1);

        return $this->pluginRepository->search($criteria, $context)->first();
    }

    private function generateYamlString(string $xmlPath): string
    {
        $container = new ContainerBuilder();
        if ($container->hasDefinition('service_container')) {
            $container->removeDefinition('service_container');
        }

        /** @phpstan-ignore new.deprecatedClass */
        $loader = new XmlFileLoader($container, new FileLocator(\dirname($xmlPath)));
        /** @phpstan-ignore method.deprecatedClass */
        $loader->load(\basename($xmlPath));

        return (new YamlDumper($container))->dump();
    }
}
