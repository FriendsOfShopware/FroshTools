<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\DatabaseDiff;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\DatabaseDiff\DatabaseIntrospectionService;
use Frosh\Tools\Components\DatabaseDiff\Swdb;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

#[CoversClass(DatabaseIntrospectionService::class)]
class DatabaseIntrospectionServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testIntrospectDatabaseSchema(): void
    {
        $version = $this->getContainer()
            ->getParameter('kernel.shopware_version');
        $service = $this->getContainer()
            ->get(DatabaseIntrospectionService::class);
        $schema  = $service->getDatabaseSchema();

        self::assertSame($version, $schema->version);
        self::assertNotEmpty($schema->tables);
    }

    public function testIntrospectCustomTable(): void
    {
        $this->ensureCustomTableExists();

        $version = $this->getContainer()
            ->getParameter('kernel.shopware_version');
        $service = $this->getContainer()
            ->get(DatabaseIntrospectionService::class);
        $schema  = $service->getDatabaseSchema();

        self::assertSame($version, $schema->version);
        self::assertNotEmpty($schema->tables);

        self::assertArrayHasKey('frosh_db_diff_test', $schema->tables);
        $table   = $schema->tables['frosh_db_diff_test'];

        self::assertInstanceOf(Swdb\Table::class, $table);
        self::assertSame('frosh_db_diff_test', $table->table);
        self::assertCount(4, $table->fields);
        self::assertEquals(['id', 'name', 'created_at', 'updated_at'], \array_keys($table->fields));
        self::assertCount(1, $table->indexes);
        self::assertEquals(['primary'], \array_keys($table->indexes));

        self::assertArrayHasKey('id', $table->fields);
        $field   = $table->fields['id'];

        self::assertInstanceOf(Swdb\TableField::class, $field);
        self::assertSame('frosh_db_diff_test', $field->table);
        self::assertSame('fields', $field->type());
        self::assertSame('frosh_db_diff_test.id', $field->key());
        self::assertSame('binary(16) not null default null /* key=PRI */', $field->value());
    }

    private function ensureCustomTableExists(): void
    {
        $this->stopTransactionAfter();

        $connection = $this->getContainer()
            ->get(Connection::class);
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `frosh_db_diff_test` (
    `id`         binary(16)   NOT NULL,
    `name`       varchar(255) NOT NULL,
    `created_at` DATETIME(3)  NOT NULL,
    `updated_at` DATETIME(3)      NULL,

    PRIMARY KEY `id` (`id`)
);
SQL
        );

        $this->startTransactionBefore();
    }
}
