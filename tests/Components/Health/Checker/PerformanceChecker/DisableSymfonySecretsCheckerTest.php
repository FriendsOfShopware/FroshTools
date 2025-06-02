<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\PerformanceChecker;

use Frosh\Tools\Components\Health\Checker\PerformanceChecker\DisableSymfonySecretsChecker;
use Frosh\Tools\Components\Health\SettingsResult;
use Frosh\Tools\Tests\Components\Health\Checker\AbstractCheckerTestCase;
use Symfony\Bundle\FrameworkBundle\Secrets\AbstractVault;

class DisableSymfonySecretsCheckerTest extends AbstractCheckerTestCase
{
    public function testCollectWithVaultEnabled(): void
    {
        $vault = $this->createMock(AbstractVault::class);
        
        $checker = new DisableSymfonySecretsChecker($vault);
        $collection = $this->createHealthCollection();
        
        $checker->collect($collection);
        
        $this->assertHealthCollectionCount($collection, 1);
        $this->assertHealthCollectionContains($collection, 'symfony-secrets', SettingsResult::INFO);
        
        $result = $this->getResultById($collection, 'symfony-secrets');
        $this->assertNotNull($result);
        $this->assertEquals('Disable Symfony Secrets', $this->getProtectedProperty($result, 'snippet'));
        $this->assertEquals('enabled', $result->current);
        $this->assertEquals('disabled', $result->recommended);
        $this->assertEquals('https://developer.shopware.com/docs/guides/hosting/performance/performance-tweaks.html#disable-symfony-secrets', $result->url);
    }

    public function testCollectWithVaultDisabled(): void
    {
        $checker = new DisableSymfonySecretsChecker(null);
        $collection = $this->createHealthCollection();
        
        $checker->collect($collection);
        
        $this->assertHealthCollectionCount($collection, 0);
    }
}