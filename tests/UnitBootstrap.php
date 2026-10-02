<?php

declare(strict_types=1);

use Composer\Autoload\ClassLoader;

/** @var ClassLoader $loader */
$loader = require __DIR__ . '/../../../../vendor/autoload.php';
$loader->addPsr4('Frosh\\Tools\\', __DIR__ . '/../src');
$loader->addPsr4('Frosh\\Tools\\Tests\\', __DIR__);
