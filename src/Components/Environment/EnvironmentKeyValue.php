<?php declare(strict_types=1);

namespace Frosh\Tools\Components\Environment;

class EnvironmentKeyValue implements EnvironmentLine
{
    private string $key;
    private string $value;

    public function getKey(): string
    {
        return $this->key;
    }

    public function setKey(string $key): void
    {
        $this->key = $key;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function setValue(string $value): void
    {
        $this->value = $value;
    }

    public function getLine(): string
    {
        return $this->key . '=' . $this->value;
    }

    public static function parse(string $line): self
    {
        $self = new self();
        [$self->key, $self->value] = explode('=', $line, 2);

        return $self;
    }
}
