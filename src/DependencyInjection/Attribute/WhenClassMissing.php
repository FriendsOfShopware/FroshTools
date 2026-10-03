<?php

declare(strict_types=1);

namespace Frosh\Tools\DependencyInjection\Attribute;

/**
 * Keep the service only when another class is not available.
 *
 * @internal
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class WhenClassMissing
{
    public function __construct(public readonly string $class)
    {
    }
}
