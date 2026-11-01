<?php

declare(strict_types=1);

namespace Frosh\Tools\Components;

use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Lock\LockFactory;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Shop confirmations saved from the Administration. File configuration stays in
 * place and wins when an id is removed here but still listed in the yaml file.
 */
final class AcknowledgementStore
{
    public const CONFIG_KEY = 'FroshTools.config.acknowledgedChecks';

    private const DATA_INTEGRITY_CACHE_KEY = 'frosh-tools-data-integrity-status';

    private const HEALTH_PING_CACHE_KEY = 'health-ping';

    private const MAX_REASON_LENGTH = 500;

    /**
     * @param array<string, mixed> $configuredChecks
     */
    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        #[Autowire(param: 'frosh_tools.checker.acknowledged_checks')]
        private readonly array $configuredChecks,
        private readonly CacheInterface $cacheObject,
        #[Autowire(service: 'lock.factory')]
        private readonly LockFactory $lockFactory,
    ) {
    }

    /**
     * File configuration first, Administration values override the same id.
     *
     * @return array<string, string>
     */
    public function all(): array
    {
        return array_merge($this->normalize($this->configuredChecks), $this->stored());
    }

    public function isConfiguredInFile(string $id): bool
    {
        return isset($this->normalize($this->configuredChecks)[$id]);
    }

    public function save(string $id, string $reason): void
    {
        $id = $this->assertId($id);
        $reason = trim($reason);
        if ($reason === '') {
            throw new \InvalidArgumentException('A reason is required.');
        }
        if (mb_strlen($reason) > self::MAX_REASON_LENGTH) {
            throw new \InvalidArgumentException('The reason is too long.');
        }

        $this->updateStored(static function (array $stored) use ($id, $reason): array {
            $stored[$id] = $reason;

            return $stored;
        });
    }

    /**
     * @return bool true when the check stays acknowledged because the yaml file still lists it
     */
    public function revoke(string $id): bool
    {
        $id = $this->assertId($id);
        $this->updateStored(static function (array $stored) use ($id): array {
            unset($stored[$id]);

            return $stored;
        });

        return $this->isConfiguredInFile($id);
    }

    /**
     * The whole map is one config value. The lock keeps a second save from
     * writing a copy that was read before the first save finished.
     *
     * @param \Closure(array<string, string>): array<string, string> $change
     */
    private function updateStored(\Closure $change): void
    {
        $lock = $this->lockFactory->createLock('frosh-tools-acknowledged-checks');
        $lock->acquire(true);

        try {
            $this->systemConfigService->set(self::CONFIG_KEY, $change($this->stored()));
            $this->clearStatusCache();
        } finally {
            $lock->release();
        }
    }

    /**
     * @return array<string, string>
     */
    private function stored(): array
    {
        $value = $this->systemConfigService->get(self::CONFIG_KEY);

        if (\is_string($value)) {
            $decoded = json_decode($value, true);
            $value = \is_array($decoded) ? $decoded : [];
        }

        return \is_array($value) ? $this->normalize($value) : [];
    }

    /**
     * @param array<mixed> $checks
     *
     * @return array<string, string>
     */
    private function normalize(array $checks): array
    {
        $normalized = [];
        foreach ($checks as $id => $reason) {
            if (!\is_string($id) || !\is_string($reason)) {
                continue;
            }
            $reason = trim($reason);
            if ($reason === '') {
                continue;
            }
            $normalized[$id] = $reason;
        }

        return $normalized;
    }

    private function assertId(string $id): string
    {
        if (!preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]{0,127}\z/', $id)) {
            throw new \InvalidArgumentException('Unknown check id.');
        }

        return $id;
    }

    private function clearStatusCache(): void
    {
        $this->cacheObject->delete(self::DATA_INTEGRITY_CACHE_KEY);
        $this->cacheObject->delete(self::HEALTH_PING_CACHE_KEY);
    }
}
