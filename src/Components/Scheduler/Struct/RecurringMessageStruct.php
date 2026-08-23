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
        public string $id = '',
        public string $scheduleName = '',
        public string $label = '',
        public string $messageClass = '',
        public string $trigger = '',
        public string $triggerType = 'other',
        public array $transports = [],
        public ?\DateTimeImmutable $nextRunDate = null,
        public bool $terminated = false,
    ) {
    }
}
