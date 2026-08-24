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
        public string $name = '',
        public bool $stateful = false,
        public ?\DateTimeImmutable $checkpoint = null,
        public array $messages = [],
        public ?string $error = null,
    ) {
    }
}
