<?php declare(strict_types=1);

namespace Frosh\Tools\Components\Environment;

class EnvironmentCommentLine implements EnvironmentLine
{
    private string $line;

    public function getLine(): string
    {
        return $this->line;
    }

    public static function parse(string $line): EnvironmentLine
    {
        $self = new self();
        $self->line = $line;

        return $self;
    }
}
