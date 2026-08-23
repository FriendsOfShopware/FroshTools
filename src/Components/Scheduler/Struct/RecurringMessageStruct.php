<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Scheduler\Struct;

use Shopware\Core\Framework\Struct\Struct;

class RecurringMessageStruct extends Struct
{
    /**
     * @param string[] $transports
     */
    public function __construct(
        protected string $id = '',
        protected string $scheduleName = '',
        protected string $label = '',
        protected string $messageClass = '',
        protected string $trigger = '',
        protected string $triggerType = 'other',
        protected array $transports = [],
        protected ?\DateTimeImmutable $nextRunDate = null,
        protected bool $terminated = false,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getScheduleName(): string
    {
        return $this->scheduleName;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getMessageClass(): string
    {
        return $this->messageClass;
    }

    public function getTrigger(): string
    {
        return $this->trigger;
    }

    public function getTriggerType(): string
    {
        return $this->triggerType;
    }

    /**
     * @return string[]
     */
    public function getTransports(): array
    {
        return $this->transports;
    }

    public function getNextRunDate(): ?\DateTimeImmutable
    {
        return $this->nextRunDate;
    }

    public function isTerminated(): bool
    {
        return $this->terminated;
    }
}
