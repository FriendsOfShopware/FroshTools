<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Security\Checker;

use Frosh\Tools\Components\Security\Checker\PasswordHasherChecker;
use Frosh\Tools\Components\Security\SecurityCollection;
use Frosh\Tools\Components\Security\SecurityFinding;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

#[CoversClass(PasswordHasherChecker::class)]
class PasswordHasherCheckerTest extends TestCase
{
    private const ALGOS_WITH_ARGON2ID = ['2y', 'argon2i', 'argon2id'];

    public function testReportsOkWhenArgon2idIsConfigured(): void
    {
        $findings = $this->collect([
            'shopware.password_hasher.customer.algorithm' => 'argon2id',
            'shopware.password_hasher.admin.algorithm' => 'argon2id',
        ], self::ALGOS_WITH_ARGON2ID);

        static::assertCount(2, $findings);
        static::assertSame(PasswordHasherChecker::ID_PREFIX . 'customer', $findings[0]->id);
        static::assertSame(PasswordHasherChecker::ID_PREFIX . 'admin', $findings[1]->id);

        foreach ($findings as $finding) {
            static::assertSame(SecurityFinding::SEVERITY_OK, $finding->severity);
            static::assertSame(SecurityFinding::CATEGORY_CONFIGURATION, $finding->category);
            static::assertSame('argon2id', $finding->current);
        }
    }

    public function testReportsLowPerScopeWithoutArgon2id(): void
    {
        $findings = $this->collect([
            'shopware.password_hasher.customer.algorithm' => 'default',
            'shopware.password_hasher.admin.algorithm' => 'argon2id',
        ], self::ALGOS_WITH_ARGON2ID);

        static::assertCount(2, $findings);

        static::assertSame(PasswordHasherChecker::ID_PREFIX . 'customer', $findings[0]->id);
        static::assertSame(SecurityFinding::SEVERITY_LOW, $findings[0]->severity);
        static::assertSame('default', $findings[0]->current);
        static::assertStringContainsString('shopware.password_hasher.customer.algorithm', $findings[0]->recommended);
        static::assertSame(PasswordHasherChecker::REFERENCE_ARGON2ID, $findings[0]->url);

        static::assertSame(SecurityFinding::SEVERITY_OK, $findings[1]->severity);
    }

    public function testReportsNothingWhenPhpLacksArgon2id(): void
    {
        $findings = $this->collect([
            'shopware.password_hasher.customer.algorithm' => 'bcrypt',
            'shopware.password_hasher.admin.algorithm' => 'bcrypt',
        ], ['2y']);

        static::assertCount(0, $findings);
    }

    public function testReportsMediumForWeakBcryptCost(): void
    {
        $findings = $this->collect([
            'shopware.password_hasher.customer.algorithm' => 'bcrypt',
            'shopware.password_hasher.customer.options' => ['cost' => 8],
            'shopware.password_hasher.admin.algorithm' => 'default',
            'shopware.password_hasher.admin.options' => ['cost' => 9],
        ], self::ALGOS_WITH_ARGON2ID);

        static::assertCount(2, $findings);

        static::assertSame(SecurityFinding::SEVERITY_MEDIUM, $findings[0]->severity);
        static::assertSame('bcrypt (cost 8)', $findings[0]->current);
        static::assertStringContainsString('shopware.password_hasher.customer.options.cost', $findings[0]->recommended);
        static::assertSame(PasswordHasherChecker::REFERENCE_BCRYPT, $findings[0]->url);

        static::assertSame(SecurityFinding::SEVERITY_MEDIUM, $findings[1]->severity);
        static::assertSame('default (cost 9)', $findings[1]->current);
    }

    public function testReportsMediumForWeakBcryptCostWithoutArgon2id(): void
    {
        $findings = $this->collect([
            'shopware.password_hasher.customer.algorithm' => 'bcrypt',
            'shopware.password_hasher.customer.options' => ['cost' => 4],
        ], ['2y']);

        static::assertCount(1, $findings);
        static::assertSame(SecurityFinding::SEVERITY_MEDIUM, $findings[0]->severity);
    }

    public function testReportsLowForSufficientBcryptCost(): void
    {
        $findings = $this->collect([
            'shopware.password_hasher.customer.algorithm' => 'bcrypt',
            'shopware.password_hasher.customer.options' => ['cost' => 10],
            'shopware.password_hasher.admin.algorithm' => 'bcrypt',
            // unresolved environment variable placeholder
            'shopware.password_hasher.admin.options' => ['cost' => 'env_abc_SHOPWARE_BCRYPT_COST'],
        ], self::ALGOS_WITH_ARGON2ID);

        static::assertCount(2, $findings);
        static::assertSame(SecurityFinding::SEVERITY_LOW, $findings[0]->severity);
        static::assertSame(SecurityFinding::SEVERITY_LOW, $findings[1]->severity);
    }

    public function testIgnoresCostOptionForArgon2id(): void
    {
        $findings = $this->collect([
            'shopware.password_hasher.customer.algorithm' => 'argon2id',
            'shopware.password_hasher.customer.options' => ['cost' => 4],
        ], self::ALGOS_WITH_ARGON2ID);

        static::assertCount(1, $findings);
        static::assertSame(SecurityFinding::SEVERITY_OK, $findings[0]->severity);
    }

    /**
     * @param array<string, mixed> $options
     */
    #[DataProvider('weakArgon2idOptions')]
    public function testReportsMediumForWeakArgon2idSettings(array $options, string $expectedCurrent, int $expectedMinMemoryCost): void
    {
        $findings = $this->collect([
            'shopware.password_hasher.customer.algorithm' => 'argon2id',
            'shopware.password_hasher.customer.options' => $options,
        ], self::ALGOS_WITH_ARGON2ID);

        static::assertCount(1, $findings);
        static::assertSame(SecurityFinding::SEVERITY_MEDIUM, $findings[0]->severity);
        static::assertSame($expectedCurrent, $findings[0]->current);
        static::assertStringContainsString('shopware.password_hasher.customer.options.memory_cost', $findings[0]->recommended);
        static::assertStringContainsString(\sprintf('at least %d KiB', $expectedMinMemoryCost), $findings[0]->recommended);
        static::assertSame(PasswordHasherChecker::REFERENCE_ARGON2ID, $findings[0]->url);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string, int}>
     */
    public static function weakArgon2idOptions(): iterable
    {
        yield 'time_cost 1 below 46 MiB' => [['memory_cost' => 47103, 'time_cost' => 1], 'argon2id (memory_cost 47103 KiB, time_cost 1)', 47104];
        yield 'time_cost 2 below 19 MiB' => [['memory_cost' => 16384, 'time_cost' => 2], 'argon2id (memory_cost 16384 KiB, time_cost 2)', 19456];
        yield 'memory_cost only, default time_cost 4' => [['memory_cost' => 8192], 'argon2id (memory_cost 8192 KiB, time_cost 4)', 9216];
        yield 'time_cost above 5 uses minimum of 5' => [['memory_cost' => 4096, 'time_cost' => 10], 'argon2id (memory_cost 4096 KiB, time_cost 10)', 7168];
    }

    /**
     * @param array<string, mixed> $options
     */
    #[DataProvider('sufficientArgon2idOptions')]
    public function testReportsOkForSufficientArgon2idSettings(array $options): void
    {
        $findings = $this->collect([
            'shopware.password_hasher.customer.algorithm' => 'argon2id',
            'shopware.password_hasher.customer.options' => $options,
        ], self::ALGOS_WITH_ARGON2ID);

        static::assertCount(1, $findings);
        static::assertSame(SecurityFinding::SEVERITY_OK, $findings[0]->severity);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function sufficientArgon2idOptions(): iterable
    {
        yield 'PHP defaults' => [[]];
        yield 'OWASP time_cost 1' => [['memory_cost' => 47104, 'time_cost' => 1]];
        yield 'OWASP time_cost 2' => [['memory_cost' => 19456, 'time_cost' => 2]];
        yield 'OWASP time_cost 5' => [['memory_cost' => 7168, 'time_cost' => 5]];
        yield 'time_cost only, default memory_cost' => [['time_cost' => 1]];
        yield 'threads are ignored' => [['memory_cost' => 19456, 'time_cost' => 2, 'threads' => 8]];
        yield 'unresolved environment variable placeholder' => [['memory_cost' => 'env_abc_SHOPWARE_ARGON2_MEMORY_COST', 'time_cost' => 1]];
    }

    public function testReportsNothingWhenShopwareHasNoPasswordHasherConfig(): void
    {
        static::assertCount(0, $this->collect([], self::ALGOS_WITH_ARGON2ID));
    }

    /**
     * @param array<string, mixed> $parameters
     * @param list<string>         $passwordAlgos
     *
     * @return list<SecurityFinding>
     */
    private function collect(array $parameters, array $passwordAlgos): array
    {
        $collection = new SecurityCollection();
        (new PasswordHasherChecker(new ParameterBag($parameters), $passwordAlgos))->collect($collection);

        /** @var list<SecurityFinding> $findings */
        $findings = array_values($collection->getElements());

        return $findings;
    }
}
