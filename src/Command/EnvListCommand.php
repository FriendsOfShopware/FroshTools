<?php

declare(strict_types=1);

namespace Frosh\Tools\Command;

use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand('frosh:env:list')]
class EnvListCommand extends EnvGetCommand {}
