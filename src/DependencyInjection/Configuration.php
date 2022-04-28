<?php

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
                ->arrayNode('elasticsearch')
                    ->children()
                        ->integerNode('product_minimum_should_match')->end()
                        ->arrayNode('product_fields')
                            ->arrayPrototype()
                                ->children()
                                    ->scalarNode('name')->end()
                                    ->booleanNode('include_in_fulltext')->defaultTrue()->end()
                                    ->booleanNode('include_in_fulltext_boosted')->defaultTrue()->end()
                                    ->variableNode('mapping')->defaultValue(['type' => 'keyword'])->end()
                                    ->arrayNode('query')
                                        ->arrayPrototype()
                                            ->children()
                                                ->scalarNode('type')->defaultValue('match')->end()
                                                ->scalarNode('bool_type')->defaultValue('should')->end()
                                                ->variableNode('options')->defaultValue([])->end()
                                            ->end()
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
