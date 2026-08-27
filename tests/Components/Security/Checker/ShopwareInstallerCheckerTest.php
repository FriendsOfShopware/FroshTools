<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Security\Checker;

use Frosh\Tools\Components\Security\Checker\ShopwareInstallerChecker;
use Frosh\Tools\Components\Security\SecurityCollection;
use Frosh\Tools\Components\Security\SecurityFinding;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ShopwareInstallerChecker::class)]
class ShopwareInstallerCheckerTest extends TestCase
{
    public function testReportsCriticalWhenInstallerIsInPublicDirectory(): void
    {
        $publicDir = $this->createPublicDir();
        file_put_contents($publicDir . '/' . ShopwareInstallerChecker::FILE_NAME, '<?php // installer');

        try {
            $finding = $this->collect($publicDir);

            static::assertSame(ShopwareInstallerChecker::ID, $finding->id);
            static::assertSame(SecurityFinding::SEVERITY_CRITICAL, $finding->severity);
            static::assertSame(SecurityFinding::CATEGORY_CONFIGURATION, $finding->category);
            static::assertSame('Shopware installer', $finding->title);
            static::assertSame('present in public directory', $finding->current);
            static::assertStringContainsString('shopware-installer.phar.php', $finding->recommended);
        } finally {
            $this->removePublicDir($publicDir);
        }
    }

    public function testReportsOkWhenInstallerIsAbsent(): void
    {
        $publicDir = $this->createPublicDir();

        try {
            $finding = $this->collect($publicDir);

            static::assertSame(SecurityFinding::SEVERITY_OK, $finding->severity);
            static::assertSame(ShopwareInstallerChecker::ID, $finding->id);
            static::assertSame('not present', $finding->current);
        } finally {
            $this->removePublicDir($publicDir);
        }
    }

    private function collect(string $publicDir): SecurityFinding
    {
        $collection = new SecurityCollection();
        (new ShopwareInstallerChecker($publicDir))->collect($collection);

        static::assertCount(1, $collection);

        /** @var SecurityFinding $finding */
        $finding = $collection->first();

        return $finding;
    }

    private function createPublicDir(): string
    {
        $publicDir = sys_get_temp_dir() . '/frosh-installer-security-' . uniqid('', true);
        static::assertTrue(mkdir($publicDir, 0777, true));

        return $publicDir;
    }

    private function removePublicDir(string $publicDir): void
    {
        $installer = $publicDir . '/' . ShopwareInstallerChecker::FILE_NAME;
        if (is_file($installer)) {
            unlink($installer);
        }

        if (is_dir($publicDir)) {
            rmdir($publicDir);
        }
    }
}
