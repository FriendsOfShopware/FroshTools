<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\DataIntegrityChecker;

use Frosh\Tools\Components\Health\Checker\DataIntegrityChecker\InvalidProductStreamChecker;
use Shopware\Core\Framework\Uuid\Uuid;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(InvalidProductStreamChecker::class)]
class InvalidProductStreamCheckerTest extends DataIntegrityCheckerTestCase
{
    private const ID = 'product-stream-invalid';

    private InvalidProductStreamChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->checker = static::getContainer()->get(InvalidProductStreamChecker::class);
        $this->connection->executeStatement('UPDATE product_stream SET invalid = 0');
    }

    public function testReportsOkWithoutInvalidProductStreams(): void
    {
        $this->createProductStream(false);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 0);
        static::assertAffectedEntities($this->checker, 'product_stream', []);
    }

    public function testCountsInvalidProductStreams(): void
    {
        $streamId = $this->createProductStream(true);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 1);
        static::assertAffectedEntities($this->checker, 'product_stream', [$streamId]);
    }

    private function createProductStream(bool $invalid): string
    {
        $id = Uuid::randomHex();

        $this->connection->insert('product_stream', [
            'id' => Uuid::fromHexToBytes($id),
            'invalid' => (int) $invalid,
            'created_at' => self::now(),
        ]);

        return $id;
    }
}
