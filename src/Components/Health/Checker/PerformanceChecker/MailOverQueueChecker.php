<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\PerformanceChecker;

use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class MailOverQueueChecker implements PerformanceCheckerInterface, CheckerInterface
{
    public function __construct(
        #[Autowire('%frosh_tools.mail_over_queue%')]
        protected bool $mailerIsOverQueue
    ) {}

    public function collect(HealthCollection $collection): void
    {
        if (!$this->mailerIsOverQueue) {
            $collection->add(
                SettingsResult::warning(
                    'mail',
                    'Sending mails over queue',
                    'disabled',
                    'enabled',
                    'https://developer.shopware.com/docs/guides/hosting/infrastructure/message-queue#sending-mails-over-the-message-queue'
                )
            );
        }
    }
}
