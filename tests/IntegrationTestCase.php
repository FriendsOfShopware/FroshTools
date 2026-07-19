<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * Base class for integration tests that need the real kernel, container and database.
 *
 * Every test runs inside a database transaction which is rolled back afterwards,
 * so tests must not use statements that cause implicit commits (e.g. TRUNCATE).
 */
abstract class IntegrationTestCase extends TestCase
{
    use KernelTestBehaviour;
    use DatabaseTransactionBehaviour;
}
