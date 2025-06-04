<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health;

use Shopware\Core\Framework\Struct\Struct;

class SettingsResult extends Struct
{
    public const GREEN = 'STATE_OK';
    public const WARNING = 'STATE_WARNING';
    public const ERROR = 'STATE_ERROR';
    public const INFO = 'STATE_INFO';

    public string $current;

    public string $recommended;

    public string $state;

    public ?string $url = null;

    protected string $id;

    public string $snippet;

    public static function ok(string $id, string $snippet, string $current = '', string $recommended = '', ?string $url = null): self
    {
        $me = new self();
        $me->id = $id;
        $me->state = self::GREEN;
        $me->snippet = $snippet;
        $me->current = $current;
        $me->recommended = $recommended;
        $me->url = $url;

        return $me;
    }

    public static function warning(string $id, string $snippet, string $current = '', string $recommended = '', ?string $url = null): self
    {
        $me = new self();
        $me->id = $id;
        $me->state = self::WARNING;
        $me->snippet = $snippet;
        $me->current = $current;
        $me->recommended = $recommended;
        $me->url = $url;

        return $me;
    }

    public static function error(string $id, string $snippet, string $current = '', string $recommended = '', ?string $url = null): self
    {
        $me = new self();
        $me->id = $id;
        $me->state = self::ERROR;
        $me->snippet = $snippet;
        $me->current = $current;
        $me->recommended = $recommended;
        $me->url = $url;

        return $me;
    }

    public static function info(string $id, string $snippet, string $current = '', string $recommended = '', ?string $url = null): self
    {
        $me = new self();
        $me->id = $id;
        $me->state = self::INFO;
        $me->snippet = $snippet;
        $me->current = $current;
        $me->recommended = $recommended;
        $me->url = $url;

        return $me;
    }

    public function getId(): string
    {
        return $this->id;
    }
}
