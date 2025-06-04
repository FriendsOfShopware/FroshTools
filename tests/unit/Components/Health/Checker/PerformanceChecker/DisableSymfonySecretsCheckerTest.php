<?php

namespace Frosh\Tools\Tests\unit\Components\Health\Checker\PerformanceChecker;

use Frosh\Tools\Components\Health\Checker\PerformanceChecker\DisableSymfonySecretsChecker;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use Frosh\Tools\Test\SettingsResultAssertionHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Secrets\AbstractVault;

#[CoversClass(DisableSymfonySecretsChecker::class)]
class DisableSymfonySecretsCheckerTest extends TestCase
{
    /**
     * @return array<string, array<string, bool>>
     */
    public static function symfonySecretsProvider(): array
    {
        return [
            'symfony secrets enabled' => [
                'vaultExists' => true,
                'expectInfo' => true,
            ],
            'symfony secrets disabled' => [
                'vaultExists' => false,
                'expectInfo' => false,
            ],
        ];
    }

    #[DataProvider('symfonySecretsProvider')]
    public function testCollect(bool $vaultExists, bool $expectInfo): void
    {
        // Create a mock for AbstractVault if needed
        $vault = $vaultExists ? $this->createMock(AbstractVault::class) : null;

        // Create the checker with our test configuration
        $checker = new DisableSymfonySecretsChecker($vault);

        // Create a health collection
        $collection = new HealthCollection();

        // Call the collect method
        $checker->collect($collection);

        if ($expectInfo) {
            // Assert that an info message was added to the collection
            SettingsResultAssertionHelper::assertSingleSettingsResult(
                $collection,
                'symfony-secrets',
                SettingsResult::INFO,
                'enabled',
                'disabled',
                'https://developer.shopware.com/docs/guides/hosting/performance/performance-tweaks.html#disable-symfony-secrets'
            );
        } else {
            // Assert that no info message was added to the collection
            SettingsResultAssertionHelper::assertSettingsResultCount($collection, 0);
        }
    }
}
