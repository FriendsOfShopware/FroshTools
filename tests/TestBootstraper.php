<?php declare(strict_types=1);

namespace Frosh\Tools\Tests;

use Shopware\Core\TestBootstrapper;

return (new TestBootstrapper())
    ->setLoadEnvFile(true)
    ->addActivePlugins("FroshTools")
    ->bootstrap()
    ->getClassLoader();

