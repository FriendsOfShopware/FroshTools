<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Security;

use Shopware\Core\Framework\Struct\Collection;

/**
 * @extends Collection<SecurityFinding>
 */
class SecurityCollection extends Collection
{
    private const SEVERITY_PRIORITY = [
        SecurityFinding::SEVERITY_CRITICAL => 0,
        SecurityFinding::SEVERITY_HIGH => 1,
        SecurityFinding::SEVERITY_MEDIUM => 2,
        SecurityFinding::SEVERITY_LOW => 3,
        SecurityFinding::SEVERITY_UNKNOWN => 4,
        SecurityFinding::SEVERITY_OK => 5,
    ];

    public function sortBySeverity(): void
    {
        $this->sort(static function (SecurityFinding $a, SecurityFinding $b) {
            return (self::SEVERITY_PRIORITY[$a->severity] ?? -1) <=> (self::SEVERITY_PRIORITY[$b->severity] ?? -1);
        });
    }

    /**
     * @param array<string> $ids
     */
    public function removeByIds(array $ids): void
    {
        $remaining = array_filter($this->getElements(), static function (SecurityFinding $finding) use ($ids) {
            return !\in_array($finding->id, $ids, true);
        });

        $this->clear();
        foreach ($remaining as $finding) {
            $this->add($finding);
        }
    }

    /**
     * @return array{critical: int, high: int, medium: int, low: int, unknown: int, ok: int}
     */
    public function countBySeverity(): array
    {
        $counts = [
            SecurityFinding::SEVERITY_CRITICAL => 0,
            SecurityFinding::SEVERITY_HIGH => 0,
            SecurityFinding::SEVERITY_MEDIUM => 0,
            SecurityFinding::SEVERITY_LOW => 0,
            SecurityFinding::SEVERITY_UNKNOWN => 0,
            SecurityFinding::SEVERITY_OK => 0,
        ];

        foreach ($this as $finding) {
            if (isset($counts[$finding->severity])) {
                ++$counts[$finding->severity];
            }
        }

        return $counts;
    }

    protected function getExpectedClass(): ?string
    {
        return SecurityFinding::class;
    }
}
