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
                ->arrayNode('elasticsearch')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('show_all_indices')
                            ->defaultFalse()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('user_recovery')
                    ->info('Administration user password recovery ("forgot password") flow. Disable it when passwords are managed externally, e.g. via SSO. Backport of shopware.api.user_recovery.enabled for Shopware versions without core support.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->defaultTrue()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
