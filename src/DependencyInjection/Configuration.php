<?php

declare(strict_types=1);

namespace Frosh\Tools\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('frosh_tools');

        $rootNode = $treeBuilder->getRootNode();
        $rootNode
            ->children()
                ->arrayNode('checker')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('disabled_checks')
                            ->scalarPrototype()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('file_checker')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('exclude_files')
                            ->scalarPrototype()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('twig_cache_warmer')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->defaultFalse()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
