<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Security\Activity;

use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[AsMessageHandler(handles: ActivityCleanupTask::class)]
class ActivityCleanupTaskHandler extends ScheduledTaskHandler
{
    /**
     * @param EntityRepository<ScheduledTaskCollection> $scheduledTaskRepository
     */
    public function __construct(
        #[Autowire(service: 'scheduled_task.repository')]
        EntityRepository $scheduledTaskRepository,
        LoggerInterface $logger,
        private readonly ActivityStore $store,
        private readonly ClockInterface $clock,
        #[Autowire(param: 'frosh_tools.security_activity.retention_days')]
        private readonly int $retentionDays,
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    public function run(): void
    {
        $this->store->deleteBefore($this->clock->now()->modify('-' . $this->retentionDays . ' days'));
    }
}
