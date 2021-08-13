<?php declare(strict_types=1);

namespace Frosh\Tools\Components\Health;

use Shopware\Core\Framework\Struct\Struct;

class HealthResult extends Struct
{
    private const GREEN = 'STATE_OK';
    private const WARNING = 'STATE_WARNING';
    private const ERROR = 'STATE_ERROR';

    /**
     * @var string
     */
    protected $state;

    /**
     * @var string
     */
    protected $snippet;

    /**
     * @var array|null
     */
    protected $parameters;

    public static function ok(string $snippet, array $parameters = []): self
    {
        $me = new self();
        $me->state = self::GREEN;
        $me->snippet = $snippet;
        $me->parameters = $parameters;

        return $me;
    }

    public static function warning(string $snippet, array $parameters = []): self
    {
        $me = new self();
        $me->state = self::WARNING;
        $me->snippet = $snippet;
        $me->parameters = $parameters;

        return $me;
    }

    public static function error(string $snippet, array $parameters = []): self
    {
        $me = new self();
        $me->state = self::ERROR;
        $me->snippet = $snippet;
        $me->parameters = $parameters;

        return $me;
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function getSnippet(): string
    {
        return $this->snippet;
    }

    public function getParameters(): ?array
    {
        return $this->parameters;
    }
}
