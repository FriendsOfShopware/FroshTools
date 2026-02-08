<?php declare(strict_types=1);

namespace Frosh\Tools\Subscriber;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

class AdminInfoListener
{
    public function __construct(
        #[Autowire('%frosh_tools.elasticsearch.enabled%')]
        private bool             $elasticsearchEnabled,
        #[Autowire(param: 'shopware.http_cache.reverse_proxy.fastly.service_id')]
        private readonly ?string $fastlyServiceId = null,
    )
    {
    }

    #[AsEventListener('api.info.config.response')]
    public function filterJson(ResponseEvent $event): void
    {
        $response = $event->getResponse();

        if (!$response->isSuccessful()) {
            return;
        }

        $data = \json_decode($response->getContent(), true);

        $data['settings']['froshTools'] = [
            'elasticsearchEnabled' => $this->elasticsearchEnabled,
            'fastlyEnabled' => !empty($this->fastlyServiceId),
        ];

        $event->getResponse()->setContent(json_encode($data, \JSON_THROW_ON_ERROR));
    }
}
