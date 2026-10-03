<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Security\Checker;

use Frosh\Tools\Components\ComposerAudit\ComposerAuditService;
use Frosh\Tools\Components\Security\Checker\DependencyAuditChecker;
use Frosh\Tools\Components\Security\SecurityCollection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DependencyAuditChecker::class)]
class DependencyAuditCheckerTest extends TestCase
{
    /**
     * @param list<string>|null $sources
     */
    #[DataProvider('sourceProvider')]
    public function testIncludesSourcesInFinding(?array $sources, string $expected): void
    {
        $advisory = ['packageName' => 'guzzlehttp/guzzle', 'installedVersion' => '7.10.0'];
        if ($sources !== null) {
            $advisory['installedSources'] = $sources;
        }
        $service = $this->createStub(ComposerAuditService::class);
        $service->method('audit')->willReturn(['packages' => 1, 'vulnerable' => 1, 'advisories' => [$advisory]]);
        $collection = new SecurityCollection();

        (new DependencyAuditChecker($service))->collect($collection);

        static::assertCount(1, $collection);
        static::assertSame('guzzlehttp/guzzle 7.10.0' . $expected, $collection->first()?->current);
    }

    public static function sourceProvider(): iterable
    {
        yield 'project dependency' => [['project'], ' — project root'];
        yield 'bundled dependency' => [['shopsy/shopsyklaviyo6'], ' — bundled from shopsy/shopsyklaviyo6'];
        yield 'shared version' => [['project', 'shopsy/shopsyklaviyo6'], ' — project root, bundled from shopsy/shopsyklaviyo6'];
        yield 'cached result without sources' => [null, ''];
        yield 'empty sources' => [[], ''];
    }
}
