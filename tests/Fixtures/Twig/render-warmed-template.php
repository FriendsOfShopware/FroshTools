<?php

declare(strict_types=1);

use Shopware\Core\Framework\Adapter\Twig\NamespaceHierarchy\NamespaceHierarchyBuilder;
use Shopware\Core\Framework\Adapter\Twig\NamespaceHierarchy\TemplateNamespaceHierarchyBuilderInterface;
use Shopware\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopware\Core\Framework\Adapter\Twig\TemplateScopeDetector;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\Source;

$config = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
foreach ($config['autoloaders'] as $autoloader) {
    require_once $autoloader;
}

$loader = new FilesystemLoader();
foreach ($config['paths'] as $namespace => $path) {
    $loader->addPath($path, $namespace);
}

// A fresh process prevents Twig's already-loaded template classes from hiding a
// cache miss. Rendering must use the warmed file, never compile the source again.
$twig = new class($loader, ['cache' => $config['cacheDir'], 'auto_reload' => false]) extends Environment {
    public function compileSource(Source $source): string
    {
        throw new RuntimeException('Warmed template was not reused: ' . $source->getName());
    }
};
$builder = new class($config['hierarchy']) implements TemplateNamespaceHierarchyBuilderInterface {
    public function __construct(private readonly array $hierarchy)
    {
    }

    public function buildNamespaceHierarchy(array $namespaceHierarchy): array
    {
        return $this->hierarchy;
    }
};
$finder = new TemplateFinder($twig, $loader, $config['cacheDir'], new NamespaceHierarchyBuilder([$builder]), new TemplateScopeDetector(new RequestStack()));
$template = $finder->find($config['template']);
echo $twig->render($template, ['name' => 'Shopware']);
