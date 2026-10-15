<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Security\Checker;

use Frosh\Tools\Components\Security\Checker\HtmlSanitizerChecker;
use Frosh\Tools\Components\Security\SecurityCollection;
use Frosh\Tools\Components\Security\SecurityFinding;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(HtmlSanitizerChecker::class)]
class HtmlSanitizerCheckerTest extends TestCase
{
    public function testReportsHighWhenSanitizerIsDisabled(): void
    {
        $collection = new SecurityCollection();

        (new HtmlSanitizerChecker(enabled: false))->collect($collection);

        static::assertCount(1, $collection);
        $finding = $collection->first();
        static::assertInstanceOf(SecurityFinding::class, $finding);
        static::assertSame(HtmlSanitizerChecker::ID, $finding->id);
        static::assertSame(SecurityFinding::CATEGORY_CONFIGURATION, $finding->category);
        static::assertSame(SecurityFinding::SEVERITY_HIGH, $finding->severity);
        static::assertSame('HTML sanitizer', $finding->title);
        static::assertSame('disabled', $finding->current);
        static::assertStringContainsString('shopware.html_sanitizer.enabled', $finding->recommended);
    }

    public function testReportsOkWhenSanitizerIsEnabled(): void
    {
        $collection = new SecurityCollection();

        (new HtmlSanitizerChecker(enabled: true))->collect($collection);

        static::assertCount(1, $collection);
        $finding = $collection->first();
        static::assertInstanceOf(SecurityFinding::class, $finding);
        static::assertSame(HtmlSanitizerChecker::ID, $finding->id);
        static::assertSame(SecurityFinding::CATEGORY_CONFIGURATION, $finding->category);
        static::assertSame(SecurityFinding::SEVERITY_OK, $finding->severity);
        static::assertSame('enabled', $finding->current);
        static::assertSame('', $finding->recommended);
    }
}
