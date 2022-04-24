<?php declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\PerformanceChecker;

use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;

class MailOverQueueChecker implements CheckerInterface
{
    protected bool $mailerIsOverQueue;

    public function __construct(bool $mailerIsOverQueue)
    {
        $this->mailerIsOverQueue = $mailerIsOverQueue;
    }

    public function collect(HealthCollection $collection): void
    {
        if (!$this->mailerIsOverQueue) {
            $collection->add(
                SettingsResult::warning('frosh-tools.checker.mailNotSendWithQueue',
                    'disabled',
                    'enabled',
                    'https://developer.shopware.com/docs/guides/hosting/infrastructure/message-queue#sending-mails-over-the-message-queue'
                )
            );

            return;
        }

        $collection->add(
            SettingsResult::ok('frosh-tools.checker.mailSendWithQueue',
                'enabled',
                'enabled'
            )
        );
    }
}
