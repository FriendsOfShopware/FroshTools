<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\PerformanceChecker;

use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;

abstract class AbstractCompressionChecker implements PerformanceCheckerInterface, CheckerInterface
{
    public const DOCUMENTATION_URL = 'https://developer.shopware.com/docs/guides/hosting/performance/performance-tweaks.html#using-zstd-instead-of-gzip-for-compression';

    public function __construct(
        private readonly string $shopwareVersion,
        private readonly string $functionality,
        private readonly bool $enabled,
        private readonly string $method,
    ) {}

    public function collect(HealthCollection $collection): void
    {
        if (\version_compare('6.6.4.0', $this->shopwareVersion, '>')) {
            return;
        }

        if (!$this->enabled) {
            $collection->add(
                SettingsResult::warning(
                    strtolower($this->functionality) . '-compress',
                    $this->functionality . ' compression',
                    'disabled',
                    'enabled',
                    self::DOCUMENTATION_URL,
                ),
            );

            return;
        }

        if ($this->method === 'gzip') {
            $collection->add(
                SettingsResult::warning(
                    strtolower($this->functionality) . '-compression-method',
                    $this->functionality . ' compression method',
                    'gzip',
                    'zstd',
                    self::DOCUMENTATION_URL,
                ),
            );

            return;
        }

        if ($this->method === 'zstd' && !extension_loaded('zstd')) {
            $collection->add(
                SettingsResult::error(
                    $this->functionality . '-compression-method-extension-zstd',
                    'PHP extension zstd for ' . $this->functionality . ' compression method',
                    'disabled',
                    'enabled',
                    self::DOCUMENTATION_URL,
                ),
            );
        }
    }
}
