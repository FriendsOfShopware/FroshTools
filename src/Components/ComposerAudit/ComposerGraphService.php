<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\ComposerAudit;

use Clue\GraphComposer\Graph\Filter as GraphComposerFilter;
use Clue\GraphComposer\Graph\GraphComposer;
use Fhaculty\Graph\Attribute\AttributeBagNamespaced;
use Fhaculty\Graph\Graph;
use Graphp\GraphViz\GraphViz;
use Shopware\Core\Framework\Adapter\Cache\CacheValueCompressor;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class ComposerGraphService
{
    public const CACHE_KEY = 'frosh-tools-composer-graph';
    public const CACHE_TTL_SECONDS = 3600;

    /**
     * @param array<array{'active': string, 'composerName': string}> $plugins
     */
    public function __construct(
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string  $projectDir,

        private readonly CacheInterface       $cacheObject,
        private readonly ComposerAuditService $composerAuditService,

        #[Autowire(param: 'kernel.plugin_infos')]
        private readonly array   $plugins,

        #[Autowire(param: 'frosh_tools.composer.graphviz_path')]
        private readonly ?string $graphvizExecutablePath = null,
    ) {
    }

    /**
     * Return a file path to the SVG graph.
     *
     * @param array<string> $packages
     */
    public function graph(
        array $packages       = [],
        bool $withDevPackages = false,
        bool $strict          = false,
        bool $forceRefresh    = false,
    ): string
    {
        $plugins  = \array_filter($this->plugins,
            static fn ($plugin) => !$plugin['active'] || !$plugin['managedByComposer']);
        $audit    = $this->composerAuditService->audit($forceRefresh);

        \array_push($packages,
            'store.shopware.com/*',
            'shopware/*',
            'frosh/*',
            ...\array_column($plugins, 'composerName'),
            ...\array_column($audit['advisories'], 'packageName'),
        );

        $packages = \array_unique($packages);

        \sort($packages);
        $cacheKey = self::CACHE_KEY
            . '_(' . \md5(\implode(',', $packages) . ($withDevPackages ? ')_dev' : ')'));

        if ($forceRefresh) {
            $this->cacheObject->delete($cacheKey);
        }

        $data = $this->cacheObject->get($cacheKey, function (ItemInterface $cacheItem) use ($audit, $packages, $withDevPackages, $strict, $forceRefresh): string {
            $cacheItem->expiresAfter(self::CACHE_TTL_SECONDS);

            // This pretty much does what `$this->graphviz->createImageData($graph)` does, while caching the graph SVG data.
            $file = $this->createGraph($audit, $packages, $withDevPackages, $strict);
            $data = \file_get_contents($file);
            \unlink($file);

            // Use compression, if enabled.
            return CacheValueCompressor::compress($data);
        });

        return CacheValueCompressor::uncompress($data);
    }

    private function createGraph(
        array $audit,
        array $packages,
        bool $withDevPackages,
        bool $strict,
    ): string
    {
        $graphviz = new GraphViz();
        $graphviz->setFormat('svg');

        if (\is_string($this->graphvizExecutablePath)
            && \is_executable($this->graphvizExecutablePath)
        ) {
            $graphviz->setExecutable($this->graphvizExecutablePath);
        }

        $graphComposer = new GraphComposer($this->projectDir, $graphviz);
        $graph = $graphComposer->createGraph(
            GraphComposerFilter::createFilter($packages, 0, $withDevPackages, $strict)
        );

        if (0 < $audit['vulnerable'] && !isset($audit['error'])) {
            $severityLimit = 'high';
            $severityOrder = ['critical' => 0, 'high' => 1, 'medium' => 2, 'moderate' => 2, 'low' => 3, '' => 4];

            // Style definition for red fill in vulnerable packages.
            $layout = [
                'style'     => 'filled, rounded',
                'fillcolor' => '#ffcccc',
                'fontcolor' => '#314B5F'
            ];

            foreach ($audit['advisories'] as $advisory) {
                // Skip on `severity > 1` or if package name is not mentioned in list of packages to show.
                if (($severityOrder[$severityLimit] < $severityOrder[$advisory['severity']] ?? 4)
                    || !\in_array($advisory['packageName'], $packages, true)
                ) {
                    continue;
                }

                $this->setGraphLayout($graph, $advisory, $layout);
            }
        }

        return $graphviz->createImageFile($graph);
    }

    /**
     * @param array{'packageName': string, 'cve': ?string, 'advisoryId': ?string} $advisory
     */
    private function setGraphLayout(Graph $graph, array $advisory, array $layout): void
    {
        $packageName = $advisory['packageName'];
        $vertex      = $graph->getVertex($packageName);
        $bag         = new AttributeBagNamespaced($vertex->getAttributeBag(), 'graphviz.');
        $identifier  = $advisory['cve'] ?: $advisory['advisoryId'];

        if ($identifier) {
            // Label definition, containing EOL and CVE identifier.
            $label  = [
                'label' => $bag->getAttribute('label', $packageName)
                    . \PHP_EOL . '(' . $identifier . ')',
            ];
            $layout = $layout + $label;
        }
        $bag->setAttributes($layout);
    }
}
