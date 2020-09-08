<?php declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker;

use Frosh\Tools\Components\Health\HealthCollection;

interface CheckerInterface
{
    public function collect(HealthCollection $collection): void;
}
