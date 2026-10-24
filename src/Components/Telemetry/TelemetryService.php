<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Telemetry;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Opt-in gate and orchestration for the daily anonymous telemetry event.
 *
 * Nothing is sent unless "FroshTools.config.telemetryEnabled" is enabled in
 * the plugin configuration. The distinct ID is a random UUID generated once
 * per installation and stored in the system config; it contains no shop,
 * host or user information.
 */
#[AutoconfigureTag('monolog.logger', ['channel' => 'frosh-tools'])]
class TelemetryService
{
    public const CONFIG_ENABLED = 'FroshTools.config.telemetryEnabled';
    public const CONFIG_INSTANCE_ID = 'FroshTools.config.telemetryInstanceId';

    public function __construct(
        private readonly SystemConfigService $configService,
        private readonly TelemetryCollector $collector,
        private readonly PostHogClient $postHogClient,
        private readonly LoggerInterface $froshToolsLogger,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->configService->getBool(self::CONFIG_ENABLED);
    }

    public function setEnabled(bool $enabled): void
    {
        $this->configService->set(self::CONFIG_ENABLED, $enabled);
    }

    public function isPostHogConfigured(): bool
    {
        return $this->postHogClient->isConfigured();
    }

    /**
     * The persisted anonymous installation ID, or null when none was created
     * yet. Unlike {@see getOrCreateInstanceId()} this never writes, so merely
     * viewing the telemetry page does not create an ID.
     */
    public function getInstanceId(): ?string
    {
        $instanceId = $this->configService->getString(self::CONFIG_INSTANCE_ID);

        return $instanceId !== '' ? $instanceId : null;
    }

    /**
     * Deletes the anonymous installation ID. The next send generates a fresh
     * one, unlinking future events from previously sent ones.
     */
    public function resetInstanceId(): void
    {
        $this->configService->delete(self::CONFIG_INSTANCE_ID);
    }

    /**
     * The exact properties that would be sent (without distinct ID / meta properties).
     *
     * @return array<string, bool|int|string>
     */
    public function preview(): array
    {
        return $this->collector->collect();
    }

    /**
     * Sends the daily event when opted in. Never throws: a failed send is
     * logged and retried by the next scheduled run.
     */
    public function send(): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        if (!$this->postHogClient->isConfigured()) {
            $this->froshToolsLogger->warning('FroshTools telemetry is enabled, but no PostHog project key is configured. Skipping send.');

            return false;
        }

        try {
            $sent = $this->postHogClient->capture($this->getOrCreateInstanceId(), $this->collector->collect());
            if (!$sent) {
                $this->froshToolsLogger->warning('FroshTools telemetry event was not accepted by PostHog.');
            }

            return $sent;
        } catch (\Throwable $e) {
            $this->froshToolsLogger->warning('FroshTools telemetry send failed: {message}', ['message' => $e->getMessage(), 'exception' => $e]);

            return false;
        }
    }

    public function getOrCreateInstanceId(): string
    {
        $instanceId = $this->configService->getString(self::CONFIG_INSTANCE_ID);
        if ($instanceId !== '') {
            return $instanceId;
        }

        $instanceId = Uuid::randomHex();
        $this->configService->set(self::CONFIG_INSTANCE_ID, $instanceId);

        return $instanceId;
    }
}
