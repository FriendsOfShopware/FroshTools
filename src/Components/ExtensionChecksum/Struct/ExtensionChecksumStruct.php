<?php declare(strict_types=1);

namespace Frosh\Tools\Components\ExtensionChecksum\Struct;

use Shopware\Core\Framework\Struct\Struct;

class ExtensionChecksumStruct extends Struct
{
    public const CURRENT_VERSION = '1.0.0';

    protected string $algorithm;

    /**
     * @var array<string, string>
     */
    protected array $hashes;

    protected ?string $version;

    protected string $extensionVersion = '';

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return (new self())->assign($data);
    }

    public function getAlgorithm(): string
    {
        return $this->algorithm;
    }

    /**
     * @return array<string, string>
     */
    public function getHashes(): array
    {
        return $this->hashes;
    }

    public function getVersion(): ?string
    {
        return $this->version;
    }

    public function getExtensionVersion(): string
    {
        return $this->extensionVersion;
    }
}
