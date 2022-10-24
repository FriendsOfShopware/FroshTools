<?php declare(strict_types=1);

namespace Frosh\Tools\Components\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class CannotClearCacheException extends ShopwareHttpException
{
    public function getErrorCode(): string
    {
        return 'FROSH_TOOLS_CANNOT_CLEAR_CACHE';
    }
}
