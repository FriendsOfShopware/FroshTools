<?php

namespace Frosh\Tools\Test;

use Frosh\Tools\Components\Health\IniReader;

class StaticIniReader extends IniReader
{
    /**
     * @var array<string, string|null|false>
     */
    private array $iniValues;

    /**
     * @param array<string, string|null|false> $iniValues
     */
    public function __construct(array $iniValues)
    {
        $this->iniValues = $iniValues;
    }

    /**
     * @return string|false
     */
    protected function getIniValue(string $key): mixed
    {
        return $this->iniValues[$key] ?? "";
    }
}
