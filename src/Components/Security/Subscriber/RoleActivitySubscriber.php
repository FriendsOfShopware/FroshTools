<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Security\Subscriber;


/**
 * @internal
 */
final class RoleActivitySubscriber extends EntityActivitySubscriber
{
    protected const ENTITY = 'acl_role';
    protected const FIELDS = ['name', 'privileges', 'deletedAt'];
}
