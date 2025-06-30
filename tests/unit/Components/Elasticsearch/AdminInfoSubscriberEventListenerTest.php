<?php

namespace Frosh\Tools\Tests\unit\Components\Elasticsearch;

use Frosh\Tools\Components\Elasticsearch\AdminInfoSubscriberEventListener;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelInterface;

#[CoversClass(AdminInfoSubscriberEventListener::class)]
class AdminInfoSubscriberEventListenerTest extends TestCase
{

    public static function subscriberElasticsearchStatusProvider(): \Generator
    {
        yield 'Elasticsearch enabled' => [true];
        yield 'Elasticsearch disabled' => [false];
    }

    #[DataProvider('subscriberElasticsearchStatusProvider')]
    public function testResponseIsExtendedWithElasticsearchData(bool $elasticsearchEnabled): void
    {
        $subscriber = new AdminInfoSubscriberEventListener($elasticsearchEnabled);

        $responseEvent = new ResponseEvent(
            $this->createMock(KernelInterface::class),
            new Request(attributes: [
                '_route' => 'api.info.config'
            ]),
            KernelInterface::MAIN_REQUEST,
            new Response("{}")
        );

        $subscriber($responseEvent);
        $response = $responseEvent->getResponse();

        self::assertSame(json_encode([
            'settings' => [
                'elasticsearchEnabled' => $elasticsearchEnabled
            ]
        ]), $response->getContent());
    }

    public function testResponseIsNotModifiedForNonInfoRoutes(): void
    {
        $subscriber = new AdminInfoSubscriberEventListener(true);
        $originalContent = '{"some":"data"}';
        
        $responseEvent = new ResponseEvent(
            $this->createMock(KernelInterface::class),
            new Request(attributes: [
                '_route' => 'some.other.route'
            ]),
            KernelInterface::MAIN_REQUEST,
            new Response($originalContent)
        );

        $subscriber($responseEvent);
        $response = $responseEvent->getResponse();

        // Verify the response content remains unchanged
        self::assertSame($originalContent, $response->getContent());
    }
}
