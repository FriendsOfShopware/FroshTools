<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Scheduler\Struct;

use Shopware\Core\Framework\Struct\Struct;

class ScheduleStruct extends Struct
{
    /**
     * @param RecurringMessageStruct[] $messages
     */
    public function __construct(
        protected string $name = '',
        protected bool $stateful = false,
        protected ?\DateTimeImmutable $checkpoint = null,
        protected array $messages = [],
        protected ?string $error = null,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isStateful(): bool
    {
        return $this->stateful;
    }

    public function getCheckpoint(): ?\DateTimeImmutable
    {
        return $this->checkpoint;
    }

    /**
     * @return RecurringMessageStruct[]
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    public function getError(): ?string
    {
        return $this->error;
    }
}
