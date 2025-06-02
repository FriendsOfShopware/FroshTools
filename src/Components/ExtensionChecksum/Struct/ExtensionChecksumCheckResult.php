<?php declare(strict_types=1);

namespace Frosh\Tools\Components\ExtensionChecksum\Struct;

use Shopware\Core\Framework\Struct\Struct;

class ExtensionChecksumCheckResult extends Struct
{
    /**
     * @param string[] $newFiles
     * @param string[] $changedFiles
     * @param string[] $missingFiles
     */
    public function __construct(
        protected bool $fileMissing = false,
        protected bool $wrongVersion = false,
        protected bool $wrongExtensionVersion = false,
        protected bool $checkFailed = false,
        protected array $newFiles = [],
        protected array $changedFiles = [],
        protected array $missingFiles = [],
    ) {
    }

    public function isExtensionOk(): bool
    {
        return !$this->wrongExtensionVersion && !$this->checkFailed && $this->newFiles === [] && $this->changedFiles === [] && $this->missingFiles === [];
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

    public function isWrongExtensionVersion(): bool
    {
        return $this->wrongExtensionVersion;
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
