<?php declare(strict_types=1);

namespace Frosh\Tools\Components\PluginChecksum;

use Frosh\Tools\Components\PluginChecksum\Struct\PluginChecksumCheckResult;
use Frosh\Tools\Components\PluginChecksum\Struct\PluginChecksumStruct;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Finder\Finder;

class PluginFileHashService
{
    /**
     * xxh128 is chosen for its excellent speed and collision resistance,
     * making it ideal for file integrity verification.
     */
    private const HASH_ALGORITHM = 'xxh128';

    private const CHECKSUM_FILE = 'checksums.json';

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $rootDir,
    ) {
    }

    public function getChecksumFilePathForPlugin(PluginEntity $plugin): string
    {
        return $this->getPluginRootPath($plugin) . '/' . self::CHECKSUM_FILE;
    }

    /**
     * @param string[] $fileExtensions
     */
    public function getChecksumData(PluginEntity $plugin, array $fileExtensions): PluginChecksumStruct
    {
        return PluginChecksumStruct::fromArray([
            'algorithm' => self::HASH_ALGORITHM,
            'fileExtensions' => $fileExtensions,
            'hashes' => $this->getHashes($plugin, $fileExtensions),
            'pluginVersion' => $plugin->getVersion(),
        ]);
    }

    public function checkPluginForChanges(PluginEntity $plugin): PluginChecksumCheckResult
    {
        $checksumFilePath = $this->getChecksumFilePathForPlugin($plugin);
        if (!is_file($checksumFilePath)) {
            return new PluginChecksumCheckResult(fileMissing: true);
        }

        if (!is_readable($checksumFilePath)) {
            throw new \RuntimeException(\sprintf('Checksum file "%s" exists but is not readable', $checksumFilePath));
        }

        try {
            $checksumFileContent = json_decode(
                (string) file_get_contents($checksumFilePath),
                true,
                512,
                \JSON_THROW_ON_ERROR
            );
        } catch (\JsonException $exception) {
            throw new \RuntimeException(\sprintf('Checksum file "%s" is not valid JSON', $checksumFilePath), 0, $exception);
        }

        $checksumFileData = PluginChecksumStruct::fromArray($checksumFileContent);
        $extensions = $checksumFileData->getFileExtensions();
        $checksumPluginVersion = $checksumFileData->getPluginVersion();

        if ($plugin->getVersion() !== $checksumPluginVersion) {
            return new PluginChecksumCheckResult(wrongVersion: true);
        }

        $currentHashes = $this->getHashes($plugin, $extensions, $checksumFileData->getAlgorithm());
        $previouslyHashedFiles = $checksumFileData->getHashes();

        $newFiles = array_diff_key($currentHashes, $previouslyHashedFiles);
        $missingFiles = array_diff_key($previouslyHashedFiles, $currentHashes);
        $changedFiles = [];
        foreach ($previouslyHashedFiles as $file => $oldHash) {
            if (isset($currentHashes[$file]) && $currentHashes[$file] !== $oldHash) {
                $changedFiles[] = $file;
            }
        }

        return new PluginChecksumCheckResult(
            newFiles: array_keys($newFiles),
            changedFiles: $changedFiles,
            missingFiles: array_keys($missingFiles),
        );
    }

    /**
     * @param string[] $extensions
     *
     * @return array<string, string>
     */
    private function getHashes(PluginEntity $plugin, array $extensions, ?string $algorithm = null): array
    {
        $algorithm = $algorithm ?? self::HASH_ALGORITHM;

        $rootPluginPath = $this->getPluginRootPath($plugin);

        $directories = $this->getDirectories($plugin);
        if ($directories === []) {
            return [];
        }

        // Normalize extensions
        $extensions = array_map(
            static fn (string $extension) => str_contains($extension, '*.') ? $extension : '*.' . $extension,
            $extensions
        );

        $finder = new Finder();
        $finder->in($directories)->files()->name($extensions);

        $hashes = [];
        foreach ($finder as $file) {
            $absoluteFilePath = $file->getRealPath();
            if (!\is_string($absoluteFilePath) || !$absoluteFilePath) {
                continue;
            }

            $hash = \hash_file($algorithm, $absoluteFilePath);
            if ($hash === false) {
                throw new \RuntimeException(\sprintf(
                    'Could not generate %s hash for "%s"',
                    $algorithm,
                    $absoluteFilePath
                ));
            }

            // Make sure the replacement handles Windows and Unix paths
            $relativePath = \ltrim(str_replace([$rootPluginPath, '\\'], ['', '/'], $absoluteFilePath), '/');

            $hashes[$relativePath] = $hash;
        }

        return $hashes;
    }

    /**
     * @return string[]
     */
    private function getDirectories(PluginEntity $plugin): array
    {
        $directories = [];

        $pluginRootPath = $this->getPluginRootPath($plugin);

        $autoload = $plugin->getAutoload();
        if ($autoload === []) {
            // Fall back to plugin root if no autoload info is available
            return [$pluginRootPath];
        }

        $psr4 = $autoload['psr-4'] ?? [];
        foreach ($psr4 as $path) {
            if (\is_string($path) && $path !== '') {
                $directories[] = $pluginRootPath . '/' . \ltrim($path, '/\\');
            }
        }

        return array_unique($directories);
    }

    private function getPluginRootPath(PluginEntity $plugin): string
    {
        return \rtrim($this->rootDir . '/' . $plugin->getPath(), '/\\');
    }
}
