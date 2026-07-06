<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\HealthChecker;

use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Checks whether the Shopware conflicts repository is registered in the root composer.json.
 *
 * Shopware publishes a Composer repository at https://shopware.github.io/conflicts/ that
 * declares known conflicting package versions. Having it in the root "repositories" lets
 * Composer refuse installing/updating to combinations that are known to break, before they
 * ever reach production. When it is missing those guard rails are gone.
 *
 * @see https://github.com/shopware/conflicts
 */
class ConflictsRepositoryChecker implements HealthCheckerInterface, CheckerInterface
{
    private const ID = 'composer-conflicts-repository';
    private const SNIPPET = 'Composer conflicts repository';
    private const REPOSITORY_URL = 'https://shopware.github.io/conflicts/';

    public function __construct(
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
    }

    public function collect(HealthCollection $collection): void
    {
        $composerFile = $this->projectDir . '/composer.json';

        if (!is_file($composerFile) || !is_readable($composerFile)) {
            $collection->add(
                SettingsResult::warning(
                    self::ID,
                    self::SNIPPET,
                    'root composer.json not found',
                    'configured',
                ),
            );

            return;
        }

        $contents = file_get_contents($composerFile);

        if ($contents === false) {
            $collection->add(
                SettingsResult::warning(
                    self::ID,
                    self::SNIPPET,
                    'root composer.json not readable',
                    'configured',
                ),
            );

            return;
        }

        try {
            $data = json_decode($contents, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $collection->add(
                SettingsResult::warning(
                    self::ID,
                    self::SNIPPET,
                    \sprintf('root composer.json is invalid: %s', $e->getMessage()),
                    'configured',
                ),
            );

            return;
        }

        if ($this->hasConflictsRepository(\is_array($data) ? ($data['repositories'] ?? []) : [])) {
            $collection->add(
                SettingsResult::ok(self::ID, self::SNIPPET, 'configured'),
            );

            return;
        }

        $collection->add(
            SettingsResult::warning(
                self::ID,
                self::SNIPPET,
                'not configured',
                'configured',
            ),
        );
    }

    /**
     * The "repositories" key may be a list (0,1,2,…) or an object keyed by name; both hold
     * entries of the shape {"type": "...", "url": "..."}.
     *
     * @param mixed $repositories
     */
    private function hasConflictsRepository(mixed $repositories): bool
    {
        if (!\is_array($repositories)) {
            return false;
        }

        foreach ($repositories as $repository) {
            if (!\is_array($repository)) {
                continue;
            }

            $type = $repository['type'] ?? null;
            $url = $repository['url'] ?? null;

            if ($type === 'composer' && \is_string($url) && $this->isConflictsUrl($url)) {
                return true;
            }
        }

        return false;
    }

    private function isConflictsUrl(string $url): bool
    {
        return rtrim($url, '/') === rtrim(self::REPOSITORY_URL, '/');
    }
}
