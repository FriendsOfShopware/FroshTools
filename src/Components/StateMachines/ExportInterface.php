<?php
declare(strict_types=1);
namespace Frosh\Tools\Components\StateMachines;

use Shopware\Core\System\StateMachine\StateMachineEntity;

interface ExportInterface
{
    /**
     * 
     * @param StateMachineEntity $stateMachine
     * @return string
     */
    public function export(StateMachineEntity $stateMachine, string $title = ''): string;
}
