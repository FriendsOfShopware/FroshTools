<?php declare(strict_types=1);

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
                        ->integerNode('product_minimum_should_match')->defaultValue(1)->end()
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
                        ->end()
                        ->arrayNode('file_checker')
                            ->children()
                                ->arrayNode('exclude_files')
                                    ->scalarPrototype()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('storefront')
                            ->children()
                                ->arrayNode('lightningcss')
                                    ->children()
                                        ->booleanNode('enabled')->defaultFalse()->end()
                                        ->scalarNode('api_url')->defaultValue('https://27uhytumuulrysydgmak3tlsgu0giwff.lambda-url.eu-central-1.on.aws')->end()
                                        ->arrayNode('browserlist')->defaultValue(['defaults'])
                                            ->scalarPrototype()
                                            ->end()
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
