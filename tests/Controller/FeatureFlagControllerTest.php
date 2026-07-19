<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Controller;

use Frosh\Tools\Controller\FeatureFlagController;
use Frosh\Tools\Tests\IntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(FeatureFlagController::class)]
class FeatureFlagControllerTest extends IntegrationTestCase
{
    public function testListReturnsActiveFlagsFirstSortedByName(): void
    {
        $controller = static::getContainer()->get(FeatureFlagController::class);
        $response = $controller->list();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $flags = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($flags);
        static::assertNotEmpty($flags);

        foreach ($flags as $flag) {
            static::assertIsArray($flag);
            static::assertArrayHasKey('flag', $flag);
            static::assertIsString($flag['flag']);
            static::assertArrayHasKey('active', $flag);
            static::assertIsBool($flag['active']);
        }

        $sorted = $flags;
        usort($sorted, static fn (array $a, array $b): int => [$b['active'], $a['flag']] <=> [$a['active'], $b['flag']]);

        static::assertSame(
            array_column($sorted, 'flag'),
            array_column($flags, 'flag'),
            'Feature flags should be sorted by active state descending, then flag name ascending'
        );
    }
}
