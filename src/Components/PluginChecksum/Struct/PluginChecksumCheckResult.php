<?php declare(strict_types=1);

namespace Frosh\Tools\Components\PluginChecksum\Struct;

use Shopware\Core\Framework\Struct\Struct;

class PluginChecksumCheckResult extends Struct
{
    /**
     * @param string[] $newFiles
     * @param string[] $changedFiles
     * @param string[] $missingFiles
     */
    public function __construct(
        protected bool $fileMissing = false,
        protected bool $wrongVersion = false,
        protected bool $wrongPluginVersion = false,
        protected bool $checkFailed = false,
        protected array $newFiles = [],
        protected array $changedFiles = [],
        protected array $missingFiles = [],
    ) {
    }

    public function isPluginOk(): bool
    {
        return !$this->wrongPluginVersion && !$this->checkFailed && $this->newFiles === [] && $this->changedFiles === [] && $this->missingFiles === [];
    }

    public function isFileMissing(): bool
    {
        return $this->fileMissing;
    }

    /**
     * Unused for now, will be needed if the checksum file format changes
     */
    public function isWrongVersion(): bool
    {
        return $this->wrongVersion;
    }

    public function isWrongPluginVersion(): bool
    {
        return $this->wrongPluginVersion;
    }

    public function isCheckFailed(): bool
    {
        return $this->checkFailed;
    }

    /**
     * @return string[]
     */
    public function getNewFiles(): array
    {
        return $this->newFiles;
    }

    /**
     * @return string[]
     */
    public function getChangedFiles(): array
    {
        return $this->changedFiles;
    }

    /**
     * @return string[]
     */
    public function getMissingFiles(): array
    {
        return $this->missingFiles;
    }
}
