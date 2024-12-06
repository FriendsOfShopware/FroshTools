<?php

declare(strict_types=1);

namespace Frosh\Tools\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class SymfonyConfigCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $mailer = $container->getDefinition('mailer.mailer');

        $container->setParameter('frosh_tools.mail_over_queue', $mailer->getArgument(1) !== null);

        $defaultTransport = $container->getDefinition('messenger.transport.async');
        $defaultHandler = $defaultTransport->getArgument(0);

        if (\is_string($defaultHandler)) {
            $container->setParameter('frosh_tools.queue_connection', $defaultHandler);
        } else {
            $container->setParameter('frosh_tools.queue_connection', 'unknown://default');
        }

        $defaultParameters = [
            'framework.secrets.enabled' => true,
            'shopware.cache.cache_compression_method' => false,
            'shopware.cache.tagging.each_config' => true,
            'shopware.cache.tagging.each_snippet' => true,
            'shopware.cache.tagging.each_theme_config' => true,
            'shopware.cart.compression_method' => false,
            'shopware.product_stream.indexing' => true,
        ];

        foreach ($defaultParameters as $parameter => $value) {
            if (!$container->hasParameter($parameter)) {
                $container->setParameter($parameter, $value);
            }
        }
    }
}
