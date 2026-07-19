<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Controller;

use Frosh\Tools\Components\Elasticsearch\ElasticsearchManager;
use Frosh\Tools\Components\Exception\FroshToolsException;
use Frosh\Tools\Controller\ElasticsearchController;
use Frosh\Tools\Tests\IntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(ElasticsearchController::class)]
class ElasticsearchControllerTest extends IntegrationTestCase
{
    public function testStatusThrowsPreconditionFailedWhenElasticsearchIsDisabled(): void
    {
        $manager = static::getContainer()->get(ElasticsearchManager::class);

        // Depending on the installation the manager is either a DisabledElasticsearchManager
        // (shopware/elasticsearch classes missing) or the real manager with
        // SHOPWARE_ES_ENABLED=0. Both report isEnabled() === false. When Elasticsearch
        // is actually enabled, status() would require a running server, so we skip.
        if ($manager->isEnabled()) {
            static::markTestSkipped('Elasticsearch is enabled, status() requires a running Elasticsearch server');
        }

        $controller = static::getContainer()->get(ElasticsearchController::class);

        try {
            $controller->status();
            static::fail('Expected FroshToolsException to be thrown');
        } catch (FroshToolsException $exception) {
            static::assertSame(Response::HTTP_PRECONDITION_FAILED, $exception->getStatusCode());
            static::assertSame('FROSH_TOOLS__ELASTICSEARCH_DISABLED', $exception->getErrorCode());
        }
    }
}
