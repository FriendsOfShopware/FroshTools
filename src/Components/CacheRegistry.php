<?php declare(strict_types=1);

namespace Frosh\Tools\Components;

class CacheRegistry
{
    /**
     * @var array<string, CacheAdapter>
     */
    private array $adapters;

    public function addAdapter(string $name, CacheAdapter $adapter): void
    {
        $this->adapters[$name] = $adapter;
    }

    public function all(): array
    {
        return $this->adapters;
    }

    public function get(string $name): CacheAdapter
    {
        if (!isset($this->adapters[$name])) {
            throw new \OutOfBoundsException(sprintf('Cannot find adapter by name %s', $name));
        }

        return $this->adapters[$name];
    }

    public function has(string $name): bool
    {
        return isset($this->adapters[$name]);
    }
}
