<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Acl;

use Frosh\Tools\Acl\FroshToolsPrivileges;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FroshToolsPrivileges::class)]
class FroshToolsPrivilegesTest extends TestCase
{
    public function testReadAndUpdateSetsDoNotOverlap(): void
    {
        $reads = FroshToolsPrivileges::allRead();
        $updates = FroshToolsPrivileges::allUpdate();

        static::assertSame($reads, array_values(array_unique($reads)));
        static::assertSame($updates, array_values(array_unique($updates)));
        static::assertSame([], array_values(array_intersect($reads, $updates)));
    }

    public function testPrivilegeStringsFollowShopwareConvention(): void
    {
        foreach (FroshToolsPrivileges::allRead() as $privilege) {
            static::assertStringEndsWith(':read', $privilege);
        }

        foreach (FroshToolsPrivileges::allUpdate() as $privilege) {
            static::assertStringEndsWith(':update', $privilege);
        }
    }
}
