<?php declare(strict_types=1);

namespace Frosh\Tools\Components\PluginChecksum;

use Frosh\Tools\Components\PluginChecksum\Struct\PluginChecksumCheckResult;
use Frosh\Tools\Components\PluginChecksum\Struct\PluginChecksumStruct;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Util\Hasher;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Finder\Finder;

class PluginFileHashService
{
    private const HASH_ALGORITHM = 'xxh128';

    private const CHECKSUM_FILE = 'checksums.json';

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $rootDir,
    ) {
    }

    public function getChecksumFilePathForPlugin(PluginEntity $plugin): string
    {
        return \rtrim($this->rootDir . '/' . $plugin->getPath(), '/\\') . '/' . self::CHECKSUM_FILE;
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

        $checksumFileContent = (string) file_get_contents($checksumFilePath);
        $checksumFileData = PluginChecksumStruct::fromArray(json_decode($checksumFileContent, true, 512, \JSON_THROW_ON_ERROR));

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
        $algorithm = $algorithm ?? Hasher::ALGO;
        $pluginPath = $plugin->getPath();
        if ($pluginPath === null) {
            return [];
        }

        $directories = $this->getDirectories($plugin);
        if ($directories === []) {
            return [];
        }

        $finder = new Finder();
        $finder->in($directories)->files()->name($extensions);

        $hashes = [];
        foreach ($finder as $file) {
            $absoluteFilePath = $file->getRealPath();
            if (!\is_string($absoluteFilePath) || !$absoluteFilePath) {
                continue;
            }

            $relativePath = (string) str_replace($this->rootDir . '/' . $pluginPath, '', $absoluteFilePath);

            $hash = \hash_file(self::HASH_ALGORITHM, $absoluteFilePath);
            if ($hash === false) {
                throw new \RuntimeException('Could not generate hash for "' . $absoluteFilePath . '"');
            }

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

        $autoload = $plugin->getAutoload();
        $psr4 = $autoload['psr-4'] ?? [];
        foreach ($psr4 as $path) {
            if (\is_string($path) && $path !== '') {
                $directories[] = \rtrim($this->rootDir . '/' . $plugin->getPath(), '/\\') . '/' . \ltrim($path, '/\\');
            }
        }

        return array_unique($directories);
    }
}
