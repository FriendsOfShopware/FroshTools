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
    }

    public function testCountsInvalidProductStreams(): void
    {
        $this->createProductStream(true);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 1);
    }

    private function createProductStream(bool $invalid): void
    {
        $this->connection->insert('product_stream', [
            'id' => Uuid::randomBytes(),
            'invalid' => (int) $invalid,
            'created_at' => self::now(),
        ]);
    }
}
