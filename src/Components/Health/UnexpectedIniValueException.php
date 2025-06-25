<?php

namespace Frosh\Tools\Components\Health;

class UnexpectedIniValueException extends \Exception
{

    public function __construct(string $key, private readonly string $actualValue)
    {
        parent::__construct(sprintf('Ini key "%s" has an unexpected value. Actual value: "%s"', $key, $actualValue));
    }

    public function getActualValue(): string
    {
        return $this->actualValue;
    }

}
