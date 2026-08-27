<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\HealthChecker;

use Frosh\Tools\Components\Health\Checker\HealthChecker\ShopwareInstallerChecker;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ShopwareInstallerChecker::class)]
class ShopwareInstallerCheckerTest extends TestCase
{
    public function testReportsErrorWhenInstallerIsInPublicDirectory(): void
    {
        $publicDir = $this->createPublicDir();
        file_put_contents($publicDir . '/' . ShopwareInstallerChecker::FILE_NAME, '<?php // installer');

        try {
            $result = $this->collect($publicDir);

            static::assertSame(SettingsResult::ERROR, $result->state);
            static::assertSame(ShopwareInstallerChecker::ID, $result->id);
            static::assertSame('Shopware installer', $result->getVars()['snippet']);
            static::assertSame('present in public directory', $result->current);
            static::assertSame('not present', $result->recommended);
        } finally {
            $this->removePublicDir($publicDir);
        }
    }

    public function testReportsOkWhenInstallerIsAbsent(): void
    {
        $publicDir = $this->createPublicDir();

        try {
            $result = $this->collect($publicDir);

            static::assertSame(SettingsResult::GREEN, $result->state);
            static::assertSame(ShopwareInstallerChecker::ID, $result->id);
            static::assertSame('not present', $result->current);
        } finally {
            $this->removePublicDir($publicDir);
        }
    }

    public function testReportsOkWhenPublicDirectoryDoesNotExist(): void
    {
        $result = $this->collect(sys_get_temp_dir() . '/frosh-missing-public-' . uniqid('', true));

        static::assertSame(SettingsResult::GREEN, $result->state);
        static::assertSame('not present', $result->current);
    }

    private function collect(string $publicDir): SettingsResult
    {
        $collection = new HealthCollection();
        (new ShopwareInstallerChecker($publicDir))->collect($collection);

        static::assertCount(1, $collection);

        /** @var SettingsResult $result */
        $result = $collection->first();

        return $result;
    }

    private function createPublicDir(): string
    {
        $publicDir = sys_get_temp_dir() . '/frosh-installer-health-' . uniqid('', true);
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
