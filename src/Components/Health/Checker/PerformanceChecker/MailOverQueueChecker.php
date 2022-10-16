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
                SettingsResult::warning('mail', 'Mails should be sent using the message queue',
                    'disabled',
                    'enabled',
                    'https://developer.shopware.com/docs/guides/hosting/infrastructure/message-queue#sending-mails-over-the-message-queue'
                )
            );

            return;
        }

        $collection->add(
            SettingsResult::ok('mail', 'Mails are send with the message queue',
                'enabled',
                'enabled'
            )
        );
    }
}
