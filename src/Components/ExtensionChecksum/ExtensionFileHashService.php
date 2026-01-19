<?php declare(strict_types=1);

namespace Frosh\Tools\Components\ExtensionChecksum;

use Frosh\Tools\Components\ExtensionChecksum\Struct\ExtensionChecksumCheckResult;
use Frosh\Tools\Components\ExtensionChecksum\Struct\ExtensionChecksumStruct;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Finder\Finder;

class ExtensionFileHashService
{
    /**
     * xxh128 is chosen for its excellent speed and collision resistance,
     * making it ideal for file integrity verification.
     */
    private const HASH_ALGORITHM = 'xxh128';

    private const CHECKSUM_FILE = 'checksum.json';

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $rootDir,
    ) {
    }

    public function getChecksumFilePathForExtension(PluginEntity $extension): string
    {
        return $this->getExtensionRootPath($extension) . '/' . self::CHECKSUM_FILE;
    }

    public function getChecksumData(PluginEntity $extension): ExtensionChecksumStruct
    {
        return ExtensionChecksumStruct::fromArray([
            'algorithm' => self::HASH_ALGORITHM,
            'hashes' => $this->getHashes($extension),
            'version' => ExtensionChecksumStruct::CURRENT_VERSION,
            'extensionVersion' => $this->normalizeVersion($extension->getVersion()),
        ]);
    }

    public function checkExtensionForChanges(PluginEntity $extension): ExtensionChecksumCheckResult
    {
        $checksumFilePath = $this->getChecksumFilePathForExtension($extension);
        if (!is_file($checksumFilePath)) {
            return new ExtensionChecksumCheckResult(fileMissing: true);
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

        $checksumFileData = ExtensionChecksumStruct::fromArray($checksumFileContent);

        // If the checksum file format changes: Add a check for $checksumFileData->getVersion() here
        // Right now the version is always 1.0.0

        $checksumExtensionVersion = $this->normalizeVersion($checksumFileData->getExtensionVersion());
        $extensionVersion = $this->normalizeVersion($extension->getVersion());

        if ($checksumExtensionVersion !== $extensionVersion) {
            return new ExtensionChecksumCheckResult(wrongExtensionVersion: true);
        }

        $currentHashes = $this->getHashes($extension, $checksumFileData->getAlgorithm());
        $previouslyHashedFiles = $checksumFileData->getHashes();

        $newFiles = array_diff_key($currentHashes, $previouslyHashedFiles);
        $missingFiles = array_diff_key($previouslyHashedFiles, $currentHashes);
        $changedFiles = [];
        foreach ($previouslyHashedFiles as $file => $oldHash) {
            if (isset($currentHashes[$file]) && $currentHashes[$file] !== $oldHash) {
                $changedFiles[] = $file;
            }
        }

        return new ExtensionChecksumCheckResult(
            newFiles: array_keys($newFiles),
            changedFiles: $changedFiles,
            missingFiles: array_keys($missingFiles),
        );
    }

    /**
     * @return array<string, string>
     */
    private function getHashes(PluginEntity $extension, ?string $algorithm = null): array
    {
        $algorithm = $algorithm ?? self::HASH_ALGORITHM;

        $extensionRootPath = $this->getExtensionRootPath($extension);

        $finder = new Finder();
        $finder->in([$extensionRootPath])
            ->files()
            ->ignoreDotFiles(false)
            ->notPath(self::CHECKSUM_FILE)
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

    private function getExtensionRootPath(PluginEntity $extension): string
    {
        return \rtrim($this->rootDir . '/' . $extension->getPath(), '/\\');
    }

    private function normalizeVersion(string $version): string
    {
        return \ltrim($version, 'v');
    }
}
