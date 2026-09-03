<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\DatabaseDiff\Swdb;

use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Util\Json;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class TableField extends Struct implements TableMemberInterface
{
    public string $table;

    public function __construct(
        public readonly string  $field,
        public readonly string  $type,
        public readonly bool    $null,
        public readonly string  $key,
        public readonly ?string $default,
        public readonly string  $extra,
    ) {
    }

    public function type(): string
    {
        return 'fields';
    }

    public function key(): string
    {
        return \sprintf('%s.%s', $this->table, $this->field);
    }

    public function value(): string
    {
        return \implode(' ', \array_filter([
            $this->type,
            $this->null ? 'null' : 'not null',
            'default ' . Json::encode($this->default),
            $this->extra,
            $this->key ? "/* key={$this->key} */" : '',
        ]));
    }

    /**
     * @param static $member
     */
    public function compare($member): bool
    {
        if (
            $this->field !== $member->field
            || $this->compareType($this->type, $member->type)
            || $this->null !== $member->null
            || $this->key !== $member->key
            || $this->compareDefault($this->default, $member->default)
            || $this->extra !== $member->extra
        ) {
            return true;
        }

        return false;
    }

    public static function compareType(string $typeA, string $typeB, bool $considerLength = false): bool
    {
        $typeA   = \strtok($typeA, '(), ');
        $lengthA = \strtok('(), ');
        $typeB   = \strtok($typeB, '(), ');
        $lengthB = \strtok('(), ');

        return ($typeA !== $typeB)
            && (!$considerLength || $lengthA !== $lengthB);
    }

    public static function compareDefault(?string $defaultA, ?string $defaultB): bool
    {
        $formatBinary = static fn ($default) => match (\substr($default, 0, 2)) {
            '0x'    => \strtolower(\substr($default, 2)),
            "x'"    => \strtok($default, "x''"),
            default => $default,
        };

        if (\is_string($defaultA)) {
            $defaultA = $formatBinary($defaultA);
        }
        if (\is_string($defaultB)) {
            $defaultB = $formatBinary($defaultB);
        }

        return $defaultA !== $defaultB;
    }

    public static function createFromData(array $data): static
    {
        return new static(
            $data['Field'],
            $data['Type'],
            $data['Null'],
            $data['Key'],
            $data['Default'],
            $data['Extra'],
        );
    }
}
