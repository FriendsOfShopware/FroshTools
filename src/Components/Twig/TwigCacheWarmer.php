<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Twig;

use Shopware\Core\Framework\Adapter\Twig\ConfigurableFilesystemCache;
use Shopware\Core\Framework\Adapter\Twig\TemplateScopeDetector;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Util\Hasher;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\KernelInterface;
use Twig\Cache\NullCache;
use Twig\Environment;
use Twig\Error\Error;

class TwigCacheWarmer
{
    /**
     * @param \IteratorAggregate<int, string> $templateIterator
     */
    public function __construct(
        private readonly Environment $twig,
        #[Autowire(service: 'twig.template_iterator')]
        private readonly \IteratorAggregate $templateIterator,
        private readonly KernelInterface $kernel,
    ) {
    }

    /**
     * @return list<string>
     */
    public function warmUp(string $cacheDir): array
    {
        if ($this->twig->getCache(false) instanceof NullCache) {
            return [];
        }

        $originalCache = $this->twig->getCache(false);
        $templates = iterator_to_array($this->templateIterator, false);
        $twigCacheDir = $cacheDir . '/twig';

        foreach ($this->getHierarchyConfigurations() as $hierarchy) {
            $configHash = Hasher::hash($hierarchy);

            $cache = new ConfigurableFilesystemCache($twigCacheDir);
            $cache->setConfigHash($configHash);
            $cache->setTemplateScopes([TemplateScopeDetector::DEFAULT_SCOPE]);

            $this->twig->setCache($cache);

            foreach ($templates as $template) {
                try {
                    $this->twig->load($template);
                } catch (Error) {
                }
            }
        }

        $this->twig->setCache($originalCache);

        if (!is_dir($twigCacheDir)) {
            return [];
        }

        $files = [];
        foreach (Finder::create()->in($twigCacheDir)->files()->name('*.php') as $file) {
            $files[] = $file->getPathname();
        }

        return $files;
    }

    /**
     * @return list<array<string, int>>
     */
    private function getHierarchyConfigurations(): array
    {
        $bundles = $this->getBundlesWithViews();

        $base = array_reverse($bundles);
        asort($base);

        $configurations = [$base];

        foreach ($this->kernel->getBundles() as $bundle) {
            if (!$bundle instanceof Bundle) {
                continue;
            }

            $themeJsonPath = $bundle->getPath() . '/Resources/theme.json';
            if (!file_exists($themeJsonPath)) {
                continue;
            }

            /** @var array{'views'?: list<string>}|false|null $themeData */
            $themeData = json_decode((string) file_get_contents($themeJsonPath), true);
            if (!\is_array($themeData)) {
                continue;
            }

            $themeName = $bundle->getName();

            $viewInheritance = $themeData['views'] ?? null;
            if (!\is_array($viewInheritance)) {
                $viewInheritance = ['@Storefront', '@Plugins', '@' . $themeName];
            }

            $reordered = $this->buildThemeHierarchy($base, $viewInheritance);
            if ($reordered !== $base) {
                $configurations[] = $reordered;
            }
        }

        return $configurations;
    }

    /**
     * @return array<string, int>
     */
    private function getBundlesWithViews(): array
    {
        $bundles = [];

        foreach ($this->kernel->getBundles() as $bundle) {
            if (!$bundle instanceof Bundle) {
                continue;
            }

            if (!is_dir($bundle->getPath() . '/Resources/views')) {
                continue;
            }

            $bundles[$bundle->getName()] = $bundle->getTemplatePriority();
        }

        return $bundles;
    }

    /**
     * @param array<string, int> $bundles
     * @param list<string> $viewInheritance
     *
     * @return array<string, int>
     */
    private function buildThemeHierarchy(array $bundles, array $viewInheritance): array
    {
        arsort($bundles);

        $inheritance = [];
        foreach ($viewInheritance as $slot) {
            $inheritance[$slot] = [];
        }

        if (!isset($inheritance['@Plugins'])) {
            $sorted = [];
            foreach ($inheritance as $index => $value) {
                $sorted[$index] = $value;
                if ($index === '@Storefront') {
                    $sorted['@Plugins'] = [];
                }
            }
            $inheritance = $sorted;
        }

        $themeBundles = $this->getThemeBundleNames();

        foreach (array_keys($bundles) as $bundle) {
            $key = '@' . $bundle;

            if (isset($inheritance[$key])) {
                $inheritance[$key][] = $bundle;

                continue;
            }

            if (isset($themeBundles[$bundle])) {
                continue;
            }

            $inheritance['@Plugins'][] = $bundle;
        }

        $inheritance['@Plugins'] = array_reverse($inheritance['@Plugins']);

        $flat = [];
        foreach ($inheritance as $namespace) {
            foreach ($namespace as $bundle) {
                $flat[] = $bundle;
            }
        }

        $flat = array_reverse($flat);

        $new = [];
        foreach ($flat as $bundle) {
            if (isset($bundles[$bundle])) {
                $new[$bundle] = $bundles[$bundle];
            }
        }

        return $new;
    }

    /**
     * @return array<string, true>
     */
    private function getThemeBundleNames(): array
    {
        $themes = [];
        foreach ($this->kernel->getBundles() as $bundle) {
            if (!$bundle instanceof Bundle) {
                continue;
            }

            if (file_exists($bundle->getPath() . '/Resources/theme.json')) {
                $themes[$bundle->getName()] = true;
            }
        }

        $themes['Storefront'] = true;

        return $themes;
    }
}
