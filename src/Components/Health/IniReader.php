<?php

namespace Frosh\Tools\Components\Health;

class IniReader
{

    /**
     * @return ($default is bool ? bool : bool|null)
     */
    public function getBoolean(string $key, ?bool $default = null): ?bool
    {
        $value = $this->getIniValue($key);

        if ($value === "" || $value === false) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL);
    }

    /**
     * @return ($default is int ? int : int|null)
     * @throws UnexpectedIniValueException
     */
    public function getInt(string $key, ?int $default = null): ?int
    {
        $value = $this->getIniValue($key);

        if ($value === "" || $default === false) {
            return $default;
        }

        if (!is_numeric($value)) {
            throw new UnexpectedIniValueException($key, $value);
        }
        return (int)$value;
    }

    /**
     * @return ($default is float ? float : float|null)
     * @throws UnexpectedIniValueException
     */
    public function getFloat(string $key, ?float $default = null): ?float
    {
        $value = $this->getIniValue($key);

        if ($value === "" || $default === false) {
            return $default;
        }

        if (!is_numeric($value)) {
            throw new UnexpectedIniValueException($key, $value);
        }
        return (float)$value;
    }

    /**
     * @return ($default is string ? string : string|null)
     */
    public function getString(string $key, ?string $default = null): ?string
    {
        $value = $this->getIniValue($key);

        if ($value === "" || $default === false) {
            return $default;
        }

        return $value;
    }

    /**
     * @return string|false
     */
    protected function getIniValue(string $key): mixed
    {
        return ini_get($key);
    }

}
