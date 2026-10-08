<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\DataIntegrityChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\DataIntegrityCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use Frosh\Tools\Tests\IntegrationTestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;

abstract class DataIntegrityCheckerTestCase extends IntegrationTestCase
{
    protected Connection $connection;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
    }

    protected function collectResult(CheckerInterface $checker, string $id): SettingsResult
    {
        $collection = new DataIntegrityCollection();
        $checker->collect($collection);

        foreach ($collection->getElements() as $element) {
            if ($element->id === $id) {
                return $element;
            }
        }

        static::fail(\sprintf('Result "%s" not found in collection', $id));
    }

    protected static function assertAffectedCount(SettingsResult $result, int $expected): void
    {
        static::assertSame($expected === 0 ? SettingsResult::GREEN : SettingsResult::INFO, $result->state);
        static::assertSame((string) $expected, $result->current);
        static::assertSame('0', $result->recommended);
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function createProduct(array $data = []): string
    {
        $id = $data['id'] ?? Uuid::randomHex();

        $payload = array_replace([
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
        ], $data);

        if (!isset($payload['parentId'])) {
            $payload += [
                'name' => 'Frosh data integrity check product',
                'taxId' => $this->connection->fetchOne('SELECT LOWER(HEX(id)) FROM tax LIMIT 1'),
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 10, 'linked' => false]],
            ];
        }

        /** @var EntityRepository $repository */
        $repository = static::getContainer()->get('product.repository');
        $repository->create([$payload], Context::createDefaultContext());

        return $id;
    }

    /**
     * @return list<string> option ids
     */
    protected function createPropertyOptions(int $count): array
    {
        $optionIds = [];
        for ($i = 0; $i < $count; ++$i) {
            $optionIds[] = Uuid::randomHex();
        }

        /** @var EntityRepository $repository */
        $repository = static::getContainer()->get('property_group.repository');
        $repository->create([[
            'name' => 'Frosh data integrity check group',
            'options' => array_map(static fn (string $id) => ['id' => $id, 'name' => $id], $optionIds),
        ]], Context::createDefaultContext());

        return $optionIds;
    }

    protected function createCmsPage(string $type): string
    {
        $id = Uuid::randomHex();

        $this->connection->insert('cms_page', [
            'id' => Uuid::fromHexToBytes($id),
            'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'type' => $type,
            'created_at' => self::now(),
        ]);

        return $id;
    }

    protected static function now(): string
    {
        return (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);
    }

    protected function updateProduct(string $id, string $column, ?string $value): void
    {
        $this->connection->executeStatement(
            \sprintf('UPDATE product SET `%s` = :value WHERE id = :id AND version_id = :liveVersionId', $column),
            [
                'value' => $value,
                'id' => Uuid::fromHexToBytes($id),
                'liveVersionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            ],
        );
    }
}
