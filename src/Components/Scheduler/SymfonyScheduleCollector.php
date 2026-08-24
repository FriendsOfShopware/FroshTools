<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Scheduler;

use Frosh\Tools\Components\Scheduler\Struct\RecurringMessageStruct;
use Frosh\Tools\Components\Scheduler\Struct\ScheduleStruct;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Message\RedispatchMessage;
use Symfony\Component\Scheduler\Generator\MessageContext;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Component\Scheduler\Trigger\CronExpressionTrigger;
use Symfony\Component\Scheduler\Trigger\JitterTrigger;
use Symfony\Component\Scheduler\Trigger\MessageProviderInterface;
use Symfony\Component\Scheduler\Trigger\PeriodicalTrigger;
use Symfony\Component\Scheduler\Trigger\StaticMessageProvider;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Service\ServiceProviderInterface;

/**
 * Reads the schedules registered by symfony/scheduler.
 *
 * The component keeps no per-task state: there is no last run, no status and no failure
 * information for a single recurring message. The only stored state is one checkpoint per
 * stateful schedule, which is also what the next run dates are computed from.
 */
class SymfonyScheduleCollector
{
    /**
     * Mirrors every `scheduled_task` row into the scheduler, so it is already covered by the
     * scheduled task list itself and would only duplicate it here.
     */
    private const SHOPWARE_BRIDGE_PROVIDER = 'Shopware\\Core\\Framework\\MessageQueue\\ScheduledTask\\SymfonyBridge\\ScheduleProvider';

    /**
     * @param ServiceProviderInterface<ScheduleProviderInterface> $schedules
     */
    public function __construct(
        #[AutowireLocator('scheduler.schedule_provider', indexAttribute: 'name')]
        private readonly ServiceProviderInterface $schedules,
    ) {
    }

    /**
     * @return ScheduleStruct[]
     */
    public function collect(): array
    {
        if (!interface_exists(ScheduleProviderInterface::class)) {
            return [];
        }

        $result = [];

        foreach (array_keys($this->schedules->getProvidedServices()) as $name) {
            $name = (string) $name;

            try {
                $provider = $this->schedules->get($name);

                if (is_a($provider, self::SHOPWARE_BRIDGE_PROVIDER)) {
                    continue;
                }

                $result[] = $this->collectSchedule($name, $provider->getSchedule());
            } catch (\Throwable $e) {
                $result[] = new ScheduleStruct($name, error: $e->getMessage());
            }
        }

        return $result;
    }

    public function findRecurringMessage(string $scheduleName, string $id): ?RecurringMessage
    {
        if (!$this->schedules->has($scheduleName)) {
            return null;
        }

        foreach ($this->schedules->get($scheduleName)->getSchedule()->getRecurringMessages() as $recurringMessage) {
            if ($recurringMessage->getId() === $id) {
                return $recurringMessage;
            }
        }

        return null;
    }

    private function collectSchedule(string $name, Schedule $schedule): ScheduleStruct
    {
        $checkpoint = $this->getCheckpointTime($schedule, $name);
        $from = $checkpoint ?? new \DateTimeImmutable();

        $messages = array_map(
            fn (RecurringMessage $recurringMessage) => $this->describe($name, $recurringMessage, $from),
            $schedule->getRecurringMessages(),
        );

        usort($messages, static function (RecurringMessageStruct $a, RecurringMessageStruct $b): int {
            $aDate = $a->nextRunDate;
            $bDate = $b->nextRunDate;

            if ($aDate === null || $bDate === null) {
                return ($aDate === null ? 1 : 0) <=> ($bDate === null ? 1 : 0);
            }

            return $aDate <=> $bDate;
        });

        return new ScheduleStruct($name, $schedule->getState() !== null, $checkpoint, $messages);
    }

    private function describe(string $scheduleName, RecurringMessage $recurringMessage, \DateTimeImmutable $from): RecurringMessageStruct
    {
        $trigger = $recurringMessage->getTrigger();
        $nextRunDate = $trigger->getNextRunDate($from);
        $provider = $recurringMessage->getProvider();

        [$label, $messageClass, $transports] = $this->describeProvider($scheduleName, $recurringMessage, $provider);

        return new RecurringMessageStruct(
            id: $recurringMessage->getId(),
            scheduleName: $scheduleName,
            label: $label,
            messageClass: $messageClass,
            trigger: (string) $trigger,
            triggerType: match (true) {
                $trigger instanceof CronExpressionTrigger => 'cron',
                $trigger instanceof PeriodicalTrigger => 'periodic',
                $trigger instanceof JitterTrigger => 'jitter',
                default => 'other',
            },
            transports: $transports,
            nextRunDate: $nextRunDate,
            terminated: $nextRunDate === null,
        );
    }

    /**
     * @return array{0: string, 1: string, 2: string[]}
     */
    private function describeProvider(string $scheduleName, RecurringMessage $recurringMessage, MessageProviderInterface $provider): array
    {
        // Only static providers can be read safely here: a custom provider may hit the
        // database or an API to yield its messages, which must not happen on a list request.
        if (!$provider instanceof StaticMessageProvider) {
            return [
                $provider instanceof \Stringable ? (string) $provider : $provider->getId(),
                $provider::class,
                [],
            ];
        }

        $context = new MessageContext($scheduleName, $recurringMessage->getId(), $recurringMessage->getTrigger(), new \DateTimeImmutable());
        $labels = [];
        $classes = [];
        $transports = [];

        foreach ($provider->getMessages($context) as $message) {
            if ($message instanceof RedispatchMessage) {
                $transports = array_merge($transports, (array) $message->transportNames);
                $message = $message->envelope instanceof Envelope ? $message->envelope->getMessage() : $message->envelope;
            }

            $classes[] = $message::class;
            $labels[] = $this->describeMessage($message);
        }

        if ($labels === []) {
            return [(string) $provider, $provider::class, []];
        }

        return [implode(', ', $labels), implode(', ', array_unique($classes)), array_values(array_unique($transports))];
    }

    private function describeMessage(object $message): string
    {
        return $message instanceof \Stringable ? (string) $message : $message::class;
    }

    /**
     * Reads a stateful schedule's last checkpoint without populating the cache.
     *
     * Mirrors the worker's checkpoint storage (key and
     * `[\DateTimeImmutable $time, int $index, \DateTimeImmutable $from]` tuple, owned by
     * {@see \Symfony\Component\Scheduler\Generator\Checkpoint}) the same way `debug:scheduler`
     * does. Best effort only: anything unexpected falls back to `null`.
     */
    private function getCheckpointTime(Schedule $schedule, string $name): ?\DateTimeImmutable
    {
        if (!$state = $schedule->getState()) {
            return null;
        }

        try {
            $checkpoint = $this->readState($state, 'scheduler_checkpoint_' . $name);
        } catch (\Throwable) {
            return null;
        }

        if (!\is_array($checkpoint)) {
            return null;
        }

        return ($checkpoint[0] ?? null) instanceof \DateTimeImmutable ? $checkpoint[0] : null;
    }

    /**
     * Reads a cache key without populating it. The return type is deliberately widened: the
     * callback only describes the miss case, while a hit returns whatever the worker stored.
     */
    private function readState(CacheInterface $state, string $key): mixed
    {
        return $state->get($key, static function (ItemInterface $item, bool &$save) {
            $save = false;

            return null;
        });
    }
}
