<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Security\Checker;

use Frosh\Tools\Components\Security\Checker\PdoParserChecker;
use Frosh\Tools\Components\Security\SecurityCollection;
use Frosh\Tools\Components\Security\SecurityFinding;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PdoParserChecker::class)]
class PdoParserCheckerTest extends TestCase
{
    public function testReportsMediumWhenPhpIsBelow84(): void
    {
        $finding = $this->collect('8.3.99');

        static::assertSame(PdoParserChecker::ID, $finding->id);
        static::assertSame(SecurityFinding::CATEGORY_RUNTIME, $finding->category);
        static::assertSame(SecurityFinding::SEVERITY_MEDIUM, $finding->severity);
        static::assertSame('8.3.99', $finding->current);
        static::assertStringContainsString('PHP 8.4 or later', $finding->recommended);
        static::assertSame(PdoParserChecker::REFERENCE, $finding->url);
    }

    public function testReportsOkWhenPhpIs84OrLater(): void
    {
        $finding = $this->collect('8.4.0');

        static::assertSame(PdoParserChecker::ID, $finding->id);
        static::assertSame(SecurityFinding::SEVERITY_OK, $finding->severity);
        static::assertSame('8.4.0', $finding->current);
        static::assertSame('', $finding->recommended);
    }

    private function collect(string $phpVersion): SecurityFinding
    {
        $collection = new SecurityCollection();
        (new PdoParserChecker($phpVersion))->collect($collection);

        static::assertCount(1, $collection);

        /** @var SecurityFinding $finding */
        $finding = $collection->first();

        return $finding;
    }
}
