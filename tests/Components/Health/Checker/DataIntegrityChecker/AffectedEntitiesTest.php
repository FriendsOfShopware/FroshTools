<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\DataIntegrityChecker;

use Doctrine\DBAL\ArrayParameterType;
use Frosh\Tools\Components\Health\Checker\DataIntegrityChecker\AffectedEntities;
use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\Framework\Uuid\Uuid;

#[CoversClass(AffectedEntities::class)]
class AffectedEntitiesTest extends DataIntegrityCheckerTestCase
{
    public function testFromIdsDeduplicatesSortsAndPages(): void
    {
        $affected = AffectedEntities::fromIds('rule', ['c', 'a', 'b', 'a'], 2, 1);

        static::assertSame('rule', $affected->entity);
        static::assertSame(3, $affected->total);
        static::assertSame(['b', 'c'], $affected->ids);
        static::assertSame(['entity' => 'rule', 'total' => 3, 'ids' => ['b', 'c']], $affected->jsonSerialize());
    }

    public function testFromQueryDeduplicatesSortsAndPages(): void
    {
        $ruleIds = [Uuid::randomHex(), Uuid::randomHex(), Uuid::randomHex()];
        foreach ($ruleIds as $ruleId) {
            $this->connection->insert('rule', [
                'id' => Uuid::fromHexToBytes($ruleId),
                'name' => 'Frosh data integrity check rule',
                'priority' => 1,
                'created_at' => self::now(),
            ]);
        }
        sort($ruleIds);

        $query = 'SELECT id FROM `rule` WHERE id IN (:ids) UNION ALL SELECT id FROM `rule` WHERE id IN (:ids)';
        $params = ['ids' => Uuid::fromHexToBytesList($ruleIds)];
        $types = ['ids' => ArrayParameterType::BINARY];

        $firstPage = AffectedEntities::fromQuery($this->connection, 'rule', $query, $params, $types, 2, 0);
        $secondPage = AffectedEntities::fromQuery($this->connection, 'rule', $query, $params, $types, 2, 2);
        $beyond = AffectedEntities::fromQuery($this->connection, 'rule', $query, $params, $types, 2, 4);

        static::assertSame(3, $firstPage->total);
        static::assertSame(\array_slice($ruleIds, 0, 2), $firstPage->ids);
        static::assertSame([$ruleIds[2]], $secondPage->ids);
        static::assertSame(3, $beyond->total);
        static::assertSame([], $beyond->ids);
    }
}
