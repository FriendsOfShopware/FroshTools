<?php declare(strict_types=1);

namespace Frosh\Tools\Components\Environment;

class EnvironmentFile
{
    /**
     * @var array<string, EnvironmentLine>
     */
    private array $items;

    public function __construct(array $items)
    {
        $this->items = $items;
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function get(string $key): ?EnvironmentKeyValue
    {
        foreach ($this->items as $item) {
            if ($item instanceof EnvironmentKeyValue && $item->getKey() === $key) {
                return $item;
            }
        }

        return null;
    }

    public function set(string $key, string $value): void
    {
        $v = $this->get($key);

        if ($v) {
            $v->setValue($value);

            return;
        }

        $this->items[] = EnvironmentKeyValue::parse($key . '=' . $value);
    }

    public function delete(string $key): void
    {
        foreach ($this->items as $i => $item) {
            if ($item instanceof EnvironmentKeyValue && $item->getKey() === $key) {
                unset($this->items[$i]);
            }
        }
    }

    public function keys(): array
    {
        $keys = [];

        foreach ($this->items as $item) {
            if ($item instanceof EnvironmentKeyValue) {
                $keys[] = $item->getKey();
            }
        }

        return $keys;
    }

    public function values(): array
    {
        $values = [];

        foreach ($this->items as $item) {
            if ($item instanceof EnvironmentKeyValue) {
                $values[$item->getKey()] = $item->getValue();
            }
        }

        return $values;
    }

    public function __toString(): string
    {
        $content = '';

        foreach ($this->items as $item) {
            $content .= $item->getLine() . \PHP_EOL;
        }

        return $content;
    }
}
