<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Elasticsearch;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

#[AutoconfigureTag('kernel.event_listener')]
final class AdminInfoSubscriberEventListener
{
    public function __construct(
        #[Autowire('%frosh_tools.elasticsearch.enabled%')]
        private readonly bool $elasticsearchEnabled
    ) {}

    public function __invoke(ResponseEvent $event): void
    {
        if ($event->getRequest()->attributes->get('_route') !== 'api.info.config') {
            return;
        }

        /** @var array{'version': string} $json */
        $json = json_decode((string) $event->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $json['settings']['elasticsearchEnabled'] = $this->elasticsearchEnabled;

        $event->getResponse()->setContent(json_encode($json, \JSON_THROW_ON_ERROR));
    }
}
