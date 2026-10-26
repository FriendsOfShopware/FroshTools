<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\DataIntegrityChecker;

interface AffectedEntitiesProviderInterface
{
    public function getId(): string;

    public function getAffectedEntities(int $limit, int $offset): AffectedEntities;
}
