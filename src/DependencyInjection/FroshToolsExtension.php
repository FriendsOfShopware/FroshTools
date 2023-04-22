<?php declare(strict_types=1);

namespace Frosh\Tools\DependencyInjection;

use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

class FroshToolsExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration($this->getConfiguration($configs, $container), $configs);
        $this->addConfig($container, $this->getAlias(), $config);
    }

    public function getConfiguration(array $config, ContainerBuilder $container): ConfigurationInterface
    {
        return new Configuration();
    }

    private function addConfig(ContainerBuilder $container, string $alias, array $options): void
    {
        foreach ($options as $key => $option) {
            $container->setParameter($alias . '.' . $key, $option);

            if (\is_array($option)) {
                $this->addConfig($container, $alias . '.' . $key, $option);
            }
        }
        
        if (!$container->hasParameter('frosh_tools.elasticsearch.product_fields')) {
            $container->setParameter('frosh_tools.elasticsearch.product_fields', []);
        }

        if (!$container->hasParameter('frosh_tools.elasticsearch.product_minimum_should_match')) {
            $container->setParameter('frosh_tools.elasticsearch.product_minimum_should_match', 1);
        }

        if (!$container->hasParameter('frosh_tools.file_checker.exclude_files')) {
            $container->setParameter('frosh_tools.file_checker.exclude_files', []);
        }
    }
}
