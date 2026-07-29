<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Security\Checker;

use Frosh\Tools\Components\Security\Checker\EolFindingFactory;
use Frosh\Tools\Components\Security\SecurityFinding;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EolFindingFactory::class)]
class EolFindingFactoryTest extends TestCase
{
    public function testUnresolvedCycleYieldsUnknown(): void
    {
        $finding = EolFindingFactory::fromCycle('php-eol', 'PHP Version', '8.3.6', null, 'https://endoflife.date/php');

        static::assertSame(SecurityFinding::SEVERITY_UNKNOWN, $finding->severity);
        static::assertSame('Could not determine end-of-life status (endoflife.date unreachable or version unknown)', $finding->recommended);
    }

    public function testUnknownEolDateYieldsUnknown(): void
    {
        $finding = EolFindingFactory::fromCycle('php-eol', 'PHP Version', '8.3.6', $this->cycle(null, eolUnknown: true), 'https://endoflife.date/php');

        static::assertSame(SecurityFinding::SEVERITY_UNKNOWN, $finding->severity);
        static::assertSame('End-of-life date is unknown for this release', $finding->recommended);
    }

    public function testEolReleaseStaysCriticalAndSaysOfficialSupportEnded(): void
    {
        $eol = new \DateTimeImmutable('-3 months');

        $finding = EolFindingFactory::fromCycle('database-eol', 'MySQL Version', '8.0.43', $this->cycle($eol, supportEnded: true), 'https://endoflife.date/mysql');

        static::assertSame('database-eol', $finding->id);
        static::assertSame(SecurityFinding::CATEGORY_RUNTIME, $finding->category);
        static::assertSame(SecurityFinding::SEVERITY_CRITICAL, $finding->severity);
        static::assertSame('MySQL Version', $finding->title);
        static::assertSame(\sprintf('8.0.43 (official end-of-life since %s)', $eol->format('Y-m-d')), $finding->current);
        static::assertSame('Upgrade to a release that still receives official security fixes', $finding->recommended);
        static::assertSame('https://endoflife.date/mysql', $finding->url);
    }

    public function testCustomUpgradeHintReplacesDefaultRecommendation(): void
    {
        $hint = 'Update Shopware to a newer version to get a supported Symfony release.';
        $eol = new \DateTimeImmutable('-1 month');

        $finding = EolFindingFactory::fromCycle('symfony-eol', 'Symfony Version', '6.4.8', $this->cycle($eol), 'https://endoflife.date/symfony', $hint);

        static::assertSame(SecurityFinding::SEVERITY_CRITICAL, $finding->severity);
        static::assertSame(\sprintf('6.4.8 (official end-of-life since %s)', $eol->format('Y-m-d')), $finding->current);
        static::assertSame($hint, $finding->recommended);
    }

    public function testCustomUpgradeHintAppliesToHighAndMediumFindings(): void
    {
        $hint = 'Update Shopware to a newer version to get a supported Symfony release.';

        $high = EolFindingFactory::fromCycle('symfony-eol', 'Symfony Version', '6.4.8', $this->cycle(new \DateTimeImmutable('+2 months')), 'https://endoflife.date/symfony', $hint);
        $medium = EolFindingFactory::fromCycle('symfony-eol', 'Symfony Version', '6.4.8', $this->cycle(new \DateTimeImmutable('+2 years'), supportEnded: true), 'https://endoflife.date/symfony', $hint);

        static::assertSame(SecurityFinding::SEVERITY_HIGH, $high->severity);
        static::assertSame($hint, $high->recommended);
        static::assertSame(SecurityFinding::SEVERITY_MEDIUM, $medium->severity);
        static::assertSame($hint, $medium->recommended);
    }

    public function testEolWithinThreeMonthsIsHigh(): void
    {
        $eol = new \DateTimeImmutable('+2 months');

        $finding = EolFindingFactory::fromCycle('php-eol', 'PHP Version', '8.2.20', $this->cycle($eol), 'https://endoflife.date/php');

        static::assertSame(SecurityFinding::SEVERITY_HIGH, $finding->severity);
        static::assertSame(\sprintf('8.2.20 (official end-of-life on %s, less than 3 months)', $eol->format('Y-m-d')), $finding->current);
        static::assertSame('Plan an upgrade before official support ends', $finding->recommended);
    }

    public function testEolBeyondThreeMonthsIsNotHigh(): void
    {
        $eol = new \DateTimeImmutable('+4 months');

        $finding = EolFindingFactory::fromCycle('php-eol', 'PHP Version', '8.2.20', $this->cycle($eol), 'https://endoflife.date/php');

        static::assertSame(SecurityFinding::SEVERITY_OK, $finding->severity);
    }

    public function testEndedActiveSupportIsMedium(): void
    {
        $finding = EolFindingFactory::fromCycle('php-eol', 'PHP Version', '8.3.6', $this->cycle(new \DateTimeImmutable('+2 years'), supportEnded: true), 'https://endoflife.date/php');

        static::assertSame(SecurityFinding::SEVERITY_MEDIUM, $finding->severity);
        static::assertSame('8.3.6 (active support ended, security fixes only)', $finding->current);
    }

    public function testSupportedReleaseIsOk(): void
    {
        $eol = new \DateTimeImmutable('+2 years');

        $finding = EolFindingFactory::fromCycle('php-eol', 'PHP Version', '8.4.8', $this->cycle($eol), 'https://endoflife.date/php');

        static::assertSame(SecurityFinding::SEVERITY_OK, $finding->severity);
        static::assertSame(\sprintf('8.4.8 (supported until %s)', $eol->format('Y-m-d')), $finding->current);
    }

    public function testSupportedReleaseWithoutEolDateIsOk(): void
    {
        $finding = EolFindingFactory::fromCycle('php-eol', 'PHP Version', '8.4.8', $this->cycle(null), 'https://endoflife.date/php');

        static::assertSame(SecurityFinding::SEVERITY_OK, $finding->severity);
        static::assertSame('8.4.8 (supported)', $finding->current);
    }

    /**
     * @return array{cycle: string, eol: \DateTimeImmutable|null, eolUnknown: bool, supportEnded: bool, latest: string|null}
     */
    private function cycle(?\DateTimeImmutable $eol, bool $eolUnknown = false, bool $supportEnded = false): array
    {
        return [
            'cycle' => '1.0',
            'eol' => $eol,
            'eolUnknown' => $eolUnknown,
            'supportEnded' => $supportEnded,
            'latest' => null,
        ];
    }
}
