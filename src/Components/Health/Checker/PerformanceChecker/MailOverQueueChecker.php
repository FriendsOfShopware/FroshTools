<?php declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\PerformanceChecker;

use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;

class MailOverQueueChecker implements CheckerInterface
{
    public function __construct(protected bool $mailerIsOverQueue)
    {
    }

    public function collect(HealthCollection $collection): void
    {
        if (!$this->mailerIsOverQueue) {
            $collection->add(
                SettingsResult::warning(
                    'mail',
                    'Mail sending',
                    'disabled',
                    'enabled',
                    'https://developer.shopware.com/docs/guides/hosting/infrastructure/message-queue#sending-mails-over-the-message-queue'
                )
            );

        }
    }
}
