<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Security\Checker;

use Frosh\Tools\Components\Security\SecurityCollection;
use Frosh\Tools\Components\Security\SecurityFinding;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * Recommends argon2id for customer and administration password hashing
 * (shopware.password_hasher.<scope>.algorithm), as long as PHP is compiled
 * with Argon2 support. Flags an explicitly configured bcrypt cost below 10
 * and argon2id memory_cost/time_cost below the OWASP minimum. threads is not
 * considered: it splits the same memory into lanes and does not change the
 * cost of a guess for an attacker.
 *
 * TODO: add a reference URL to the findings once Shopware documents the password_hasher configuration
 */
class PasswordHasherChecker implements SecurityCheckerInterface
{
    public const ID_PREFIX = 'password-hasher-';
    public const REFERENCE_ARGON2ID = 'https://cheatsheetseries.owasp.org/cheatsheets/Password_Storage_Cheat_Sheet.html#argon2id';
    public const REFERENCE_BCRYPT = 'https://cheatsheetseries.owasp.org/cheatsheets/Password_Storage_Cheat_Sheet.html#bcrypt';

    private const ARGON2ID = 'argon2id';
    private const BCRYPT = 'bcrypt';
    private const DEFAULT = 'default';

    /**
     * Minimum bcrypt cost recommended by OWASP.
     */
    private const MIN_BCRYPT_COST = 10;

    /**
     * Minimum argon2id memory_cost in KiB per time_cost, following the equivalent
     * configurations recommended by OWASP. A time_cost above 5 uses the value of 5.
     *
     * @var array<int, int>
     */
    private const MIN_ARGON2ID_MEMORY_COST = [
        1 => 47104,
        2 => 19456,
        3 => 12288,
        4 => 9216,
        5 => 7168,
    ];

    private const SCOPES = [
        'customer' => 'Customer password hashing',
        'admin' => 'Administration password hashing',
    ];

    /**
     * @var list<string>
     */
    private readonly array $passwordAlgos;

    /**
     * @param list<string>|null $passwordAlgos defaults to password_algos()
     */
    public function __construct(
        private readonly ParameterBagInterface $parameterBag,
        ?array $passwordAlgos = null,
    ) {
        $this->passwordAlgos = $passwordAlgos ?? password_algos();
    }

    public function collect(SecurityCollection $collection): void
    {
        $argon2idAvailable = \in_array(self::ARGON2ID, $this->passwordAlgos, true);

        foreach (self::SCOPES as $scope => $title) {
            $parameter = \sprintf('shopware.password_hasher.%s.algorithm', $scope);

            // Shopware versions without configurable password hashing have no such parameter.
            if (!$this->parameterBag->has($parameter)) {
                continue;
            }

            $algorithm = $this->parameterBag->get($parameter);
            $algorithm = \is_string($algorithm) ? $algorithm : 'unknown';

            // A weak bcrypt cost is a problem on its own, regardless of argon2id support.
            $bcryptCost = $this->getConfiguredBcryptCost($scope, $algorithm);
            if ($bcryptCost !== null && $bcryptCost < self::MIN_BCRYPT_COST) {
                $collection->add(SecurityFinding::medium(
                    self::ID_PREFIX . $scope,
                    SecurityFinding::CATEGORY_CONFIGURATION,
                    $title,
                    \sprintf('%s (cost %d)', $algorithm, $bcryptCost),
                    \sprintf(
                        'Raise shopware.password_hasher.%s.options.cost to at least %d or remove it to use the PHP default.',
                        $scope,
                        self::MIN_BCRYPT_COST,
                    ),
                    self::REFERENCE_BCRYPT,
                ));

                continue;
            }

            $argon2idSettings = $this->getWeakArgon2idSettings($scope, $algorithm);
            if ($argon2idSettings !== null) {
                [$memoryCost, $timeCost] = $argon2idSettings;

                $collection->add(SecurityFinding::medium(
                    self::ID_PREFIX . $scope,
                    SecurityFinding::CATEGORY_CONFIGURATION,
                    $title,
                    \sprintf('%s (memory_cost %d KiB, time_cost %d)', $algorithm, $memoryCost, $timeCost),
                    \sprintf(
                        'Raise shopware.password_hasher.%s.options.memory_cost to at least %d KiB or remove it to use the PHP default.',
                        $scope,
                        $this->getMinArgon2idMemoryCost($timeCost),
                    ),
                    self::REFERENCE_ARGON2ID,
                ));

                continue;
            }

            // Without argon2id support in PHP there is nothing to recommend.
            if (!$argon2idAvailable) {
                continue;
            }

            if ($algorithm === self::ARGON2ID) {
                $collection->add(SecurityFinding::ok(
                    self::ID_PREFIX . $scope,
                    SecurityFinding::CATEGORY_CONFIGURATION,
                    $title,
                    $algorithm,
                    self::ARGON2ID,
                ));

                continue;
            }

            $collection->add(SecurityFinding::low(
                self::ID_PREFIX . $scope,
                SecurityFinding::CATEGORY_CONFIGURATION,
                $title,
                $algorithm,
                \sprintf(
                    'Set %s to argon2id, which resists GPU cracking better than bcrypt.',
                    $parameter,
                ),
                self::REFERENCE_ARGON2ID,
            ));
        }
    }

    /**
     * Returns the explicitly configured bcrypt cost, or null when bcrypt is not used or no cost is set
     * (the PHP default of 10, or 12 since PHP 8.4, is fine). Costs from environment variables are not resolved yet and skipped.
     */
    private function getConfiguredBcryptCost(string $scope, string $algorithm): ?int
    {
        // "default" maps to PASSWORD_DEFAULT, which is bcrypt on every supported PHP version.
        if ($algorithm !== self::BCRYPT && $algorithm !== self::DEFAULT) {
            return null;
        }

        $cost = $this->getOptions($scope)['cost'] ?? null;

        return \is_int($cost) ? $cost : null;
    }

    /**
     * Returns [memory_cost, time_cost] when argon2id is configured below the OWASP minimum, otherwise null.
     * Options that are not configured fall back to the PHP defaults; values from environment variables are not resolved yet and skipped.
     *
     * @return array{int, int}|null
     */
    private function getWeakArgon2idSettings(string $scope, string $algorithm): ?array
    {
        if ($algorithm !== self::ARGON2ID) {
            return null;
        }

        $options = $this->getOptions($scope);
        $memoryCost = $options['memory_cost'] ?? \PASSWORD_ARGON2_DEFAULT_MEMORY_COST;
        $timeCost = $options['time_cost'] ?? \PASSWORD_ARGON2_DEFAULT_TIME_COST;

        if (!\is_int($memoryCost) || !\is_int($timeCost) || $timeCost < 1) {
            return null;
        }

        if ($memoryCost >= $this->getMinArgon2idMemoryCost($timeCost)) {
            return null;
        }

        return [$memoryCost, $timeCost];
    }

    private function getMinArgon2idMemoryCost(int $timeCost): int
    {
        return self::MIN_ARGON2ID_MEMORY_COST[min($timeCost, 5)] ?? self::MIN_ARGON2ID_MEMORY_COST[1];
    }

    /**
     * @return array<mixed>
     */
    private function getOptions(string $scope): array
    {
        $parameter = \sprintf('shopware.password_hasher.%s.options', $scope);
        if (!$this->parameterBag->has($parameter)) {
            return [];
        }

        $options = $this->parameterBag->get($parameter);

        return \is_array($options) ? $options : [];
    }
}
