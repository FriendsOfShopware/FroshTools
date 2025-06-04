<?php

namespace Frosh\Tools\Tests\integration\Components\Elasticsearch;

use Frosh\Tools\Components\Elasticsearch\AdminInfoSubscriberEventListener;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(AdminInfoSubscriberEventListener::class)]
class AdminInfoSubscriberEventListenerTest extends TestCase
{

    use IntegrationTestBehaviour;
    use AdminApiTestBehaviour;
    public function testApiInfoIsExtendedWithElasticsearchInfo(): void
    {
        $browser = $this->getBrowser();
        $browser->request('GET', '/api/_info/config');
        $response = $browser->getResponse();

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $response = json_decode($browser->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertArrayHasKey('elasticsearchEnabled', $response['settings']);
    }

}
