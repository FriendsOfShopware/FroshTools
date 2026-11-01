<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Command;

use Frosh\Tools\Command\DataIntegrityCheckJsonCommand;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(DataIntegrityCheckJsonCommand::class)]
class DataIntegrityCheckJsonCommandTest extends TestCase
{
    public function testPrintsSortedResultsWithoutDisabledChecks(): void
    {
        $checker = new class implements CheckerInterface {
            public function collect(HealthCollection $collection): void
            {
                $collection->add(SettingsResult::ok('ok-check', 'OK check', '0', '0'));
                $collection->add(SettingsResult::info('info-check', 'Info check', '3', '0'));
                $collection->add(SettingsResult::info('disabled-check', 'Disabled check', '1', '0'));
            }
        };

        $tester = new CommandTester(new DataIntegrityCheckJsonCommand([$checker], ['disabled-check']));

        static::assertSame(Command::SUCCESS, $tester->execute([]));

        $entries = json_decode($tester->getDisplay(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($entries);
        static::assertSame(['info-check', 'ok-check'], array_column($entries, 'id'));
    }

    public function testAcknowledgedCheckBecomesInfoUnlessItIsDisabled(): void
    {
        $checker = new class implements CheckerInterface {
            public function collect(HealthCollection $collection): void
            {
                $collection->add(SettingsResult::error('php-version', 'PHP Version', '8.3.6', 'min 8.3.36'));
                $collection->add(SettingsResult::warning('hidden', 'Hidden', 'on', 'off'));
                $collection->add(SettingsResult::error('kept-error', 'Kept', 'bad', 'good'));
            }
        };

        $reason = 'Ubuntu 24.04 revision 8.3.6-0ubuntu0.24.04.13 contains the PHP 8.3.36 security fixes';
        $tester = new CommandTester(new DataIntegrityCheckJsonCommand(
            [$checker],
            ['hidden'],
            [
                'php-version' => $reason,
                'hidden' => 'Disabled wins',
            ],
        ));

        static::assertSame(Command::SUCCESS, $tester->execute([]));

        $entries = json_decode($tester->getDisplay(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($entries);
        static::assertSame(['kept-error', 'php-version'], array_column($entries, 'id'));

        $php = $entries[1];
        static::assertSame(SettingsResult::INFO, $php['state']);
        static::assertSame('8.3.6', $php['current']);
        static::assertSame('min 8.3.36', $php['recommended']);
        static::assertSame($reason, $php['acknowledgement']);
        static::assertSame('', $entries[0]['acknowledgement']);
    }
}
