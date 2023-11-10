<?php

namespace Frosh\Tools\Components\SystemConfig;

use Shopware\Core\System\SystemConfig\AbstractSystemConfigLoader;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsDecorator(
    decorates: 'Shopware\Core\System\SystemConfig\SystemConfigLoader',
    priority: 2000,
)]
class ConfigSystemConfigLoader extends AbstractSystemConfigLoader
{
    public function __construct(private readonly AbstractSystemConfigLoader $decorated, #[Autowire('%frosh_tools.system_config%')] private readonly array $config) {}

    public function getDecorated(): AbstractSystemConfigLoader
    {
        return $this->decorated;
    }

    public function load(?string $salesChannelId): array
    {
        $config = $this->decorated->load($salesChannelId);

        $specific = array_merge(
            $this->config['default'] ?? [],
            $this->config[$salesChannelId] ?? []
        );

        foreach ($specific as $key => $value) {
            $keys = \explode('.', (string)$key);

            $specific = $this->getSubArray($specific, $keys, $value);

            unset($specific[$key]);
        }

        return array_replace_recursive($config, $specific);
    }

    /**
     * @param array|bool|float|int|string|null $value
     */
    private function getSubArray(array $configValues, array $keys, mixed $value): array
    {
        $key = \array_shift($keys);

        if (empty($keys)) {
            // Configs can be overwritten with sales_channel_id
            $inheritedValuePresent = \array_key_exists($key, $configValues);
            $valueConsideredEmpty = !\is_bool($value) && empty($value);

            if ($inheritedValuePresent && $valueConsideredEmpty) {
                return $configValues;
            }

            $configValues[$key] = $value;
        } else {
            if (!\array_key_exists($key, $configValues)) {
                $configValues[$key] = [];
            }

            $configValues[$key] = $this->getSubArray($configValues[$key], $keys, $value);
        }

        return $configValues;
    }
}
