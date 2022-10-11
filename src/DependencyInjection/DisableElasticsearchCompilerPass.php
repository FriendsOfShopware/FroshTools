<?php declare(strict_types=1);

namespace Frosh\Tools\DependencyInjection;

use Elasticsearch\Client;
use Frosh\Tools\Components\Elasticsearch\DisabledElasticsearchManager;
use Frosh\Tools\Components\Elasticsearch\ElasticsearchManager;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class DisableElasticsearchCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if ($container->hasDefinition(Client::class)) {
            $container->setParameter('frosh_tools.elasticsearch.enabled', true);

            return;
        }

        $manager = $container->getDefinition(ElasticsearchManager::class);
        $manager->setClass(DisabledElasticsearchManager::class);
        $manager->setArguments([]);
        $container->setParameter('frosh_tools.elasticsearch.enabled', false);
    }
}
