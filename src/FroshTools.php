<?php declare(strict_types=1);

namespace Frosh\Tools;

use Shopware\Core\Framework\Plugin;

if (file_exists($vendorPath = __DIR__ . '/../vendor/autoload.php')) {
    require_once $vendorPath;
}

class FroshTools extends Plugin
{
}
