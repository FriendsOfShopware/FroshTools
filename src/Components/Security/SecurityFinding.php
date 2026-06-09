<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Security;

use Shopware\Core\Framework\Struct\Struct;

class SecurityFinding extends Struct
{
    public const SEVERITY_CRITICAL = 'critical';
    public const SEVERITY_HIGH = 'high';
    public const SEVERITY_MEDIUM = 'medium';
    public const SEVERITY_LOW = 'low';
    public const SEVERITY_OK = 'ok';
    public const SEVERITY_UNKNOWN = 'unknown';

    public const CATEGORY_DEPENDENCIES = 'dependencies';
    public const CATEGORY_RUNTIME = 'runtime';
    public const CATEGORY_UPDATES = 'updates';
    public const CATEGORY_CONFIGURATION = 'configuration';

    public string $id;

    public string $category;

    public string $severity;

    public string $title;

    public string $current = '';

    public string $recommended = '';

    public ?string $url = null;

    public function __construct(
        string $id,
        string $category,
        string $severity,
        string $title,
        string $current = '',
        string $recommended = '',
        ?string $url = null,
    ) {
        $this->id = $id;
        $this->category = $category;
        $this->severity = $severity;
        $this->title = $title;
        $this->current = $current;
        $this->recommended = $recommended;
        $this->url = $url;
    }

    public static function critical(string $id, string $category, string $title, string $current = '', string $recommended = '', ?string $url = null): self
    {
        return new self($id, $category, self::SEVERITY_CRITICAL, $title, $current, $recommended, $url);
    }

    public static function high(string $id, string $category, string $title, string $current = '', string $recommended = '', ?string $url = null): self
    {
        return new self($id, $category, self::SEVERITY_HIGH, $title, $current, $recommended, $url);
    }

    public static function medium(string $id, string $category, string $title, string $current = '', string $recommended = '', ?string $url = null): self
    {
        return new self($id, $category, self::SEVERITY_MEDIUM, $title, $current, $recommended, $url);
    }

    public static function low(string $id, string $category, string $title, string $current = '', string $recommended = '', ?string $url = null): self
    {
        return new self($id, $category, self::SEVERITY_LOW, $title, $current, $recommended, $url);
    }

    public static function ok(string $id, string $category, string $title, string $current = '', string $recommended = '', ?string $url = null): self
    {
        return new self($id, $category, self::SEVERITY_OK, $title, $current, $recommended, $url);
    }

    public static function unknown(string $id, string $category, string $title, string $current = '', string $recommended = '', ?string $url = null): self
    {
        return new self($id, $category, self::SEVERITY_UNKNOWN, $title, $current, $recommended, $url);
    }
}
