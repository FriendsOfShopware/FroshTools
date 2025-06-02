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
        return $this->getExtensionRootPath($plugin) . '/' . self::CHECKSUM_FILE;
    }

    public function getChecksumData(PluginEntity $plugin): PluginChecksumStruct
    {
        return PluginChecksumStruct::fromArray([
            'algorithm' => self::HASH_ALGORITHM,
            'hashes' => $this->getHashes($plugin),
            'version' => PluginChecksumStruct::CURRENT_VERSION,
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

        // If the checksum file format changes: Add a check for $checksumFileData->getVersion() here
        // Right now the version is always 1.0.0

        if ($checksumFileData->getPluginVersion() !== $plugin->getVersion()) {
            return new PluginChecksumCheckResult(wrongPluginVersion: true);
        }

        $currentHashes = $this->getHashes($plugin, $checksumFileData->getAlgorithm());
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
     * @return array<string, string>
     */
    private function getHashes(PluginEntity $plugin, ?string $algorithm = null): array
    {
        $algorithm = $algorithm ?? self::HASH_ALGORITHM;

        $extensionRootPath = $this->getExtensionRootPath($plugin);

        $finder = new Finder();
        $finder->in([$extensionRootPath])
            ->files()
            ->ignoreDotFiles(false)
            ->notPath('checksums.json')
            ->notPath('Resources/public/administration')
            ->notPath('/vendor/')
            ->notPath('/node_modules/');

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
            $relativePath = \ltrim(str_replace([$extensionRootPath, '\\'], ['', '/'], $absoluteFilePath), '/');

            $hashes[$relativePath] = $hash;
        }

        ksort($hashes);

        return $hashes;
    }

    private function getExtensionRootPath(PluginEntity $plugin): string
    {
        return \rtrim($this->rootDir . '/' . $plugin->getPath(), '/\\');
    }
}
