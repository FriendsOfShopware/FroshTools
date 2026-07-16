<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Queue;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;

class QueueRegistry
{
    private const TRANSPORT_ID_PREFIX = 'messenger.transport.';

    /**
     * @var array<string, QueueAdapter>|null
     */
    private ?array $adapters = null;

    /**
     * @param ServiceLocator<ReceiverInterface> $receiverLocator
     */
    public function __construct(
        #[Autowire(service: 'messenger.receiver_locator')]
        private readonly ServiceLocator $receiverLocator,
        private readonly MessageBusInterface $bus,
    ) {
    }

    /**
     * @return array<string, QueueAdapter>
     */
    public function all(): array
    {
        if ($this->adapters !== null) {
            return $this->adapters;
        }

        $this->adapters = [];

        foreach ($this->getTransportNames() as $name) {
            $transport = $this->receiverLocator->get($name);
            $this->adapters[$name] = $this->createAdapter($name, $transport);
        }

        return $this->adapters;
    }

    public function get(string $name): QueueAdapter
    {
        $adapters = $this->all();

        if (!isset($adapters[$name])) {
            throw new \OutOfBoundsException(\sprintf('Cannot find queue adapter by name %s', $name));
        }

        return $adapters[$name];
    }

    public function has(string $name): bool
    {
        return isset($this->all()[$name]);
    }

    private function createAdapter(string $name, ReceiverInterface $transport): QueueAdapter
    {
        if (DoctrineQueueAdapter::supports($transport)) {
            \assert($transport instanceof \Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineTransport);

            return new DoctrineQueueAdapter($name, $transport, $this->bus);
        }

        if ($transport instanceof ListableReceiverInterface) {
            return new ListableQueueAdapter($name, $transport, $this->bus);
        }

        if (RedisQueueAdapter::supports($transport)) {
            return new RedisQueueAdapter($name, $transport);
        }

        if (AmqpQueueAdapter::supports($transport)) {
            return new AmqpQueueAdapter($name, $transport);
        }

        return new FallbackQueueAdapter($name, $transport);
    }

    /**
     * The receiver locator contains every transport twice: once by service id
     * (messenger.transport.async) and once by its alias (async). Prefer the alias.
     *
     * @return list<string>
     */
    private function getTransportNames(): array
    {
        $names = array_keys($this->receiverLocator->getProvidedServices());

        $aliases = array_filter($names, static fn (string $name) => !str_starts_with($name, self::TRANSPORT_ID_PREFIX));

        $missing = [];
        foreach ($names as $name) {
            if (!str_starts_with($name, self::TRANSPORT_ID_PREFIX)) {
                continue;
            }

            $alias = substr($name, \strlen(self::TRANSPORT_ID_PREFIX));
            if (!\in_array($alias, $aliases, true)) {
                $missing[] = $name;
            }
        }

        $names = [...$aliases, ...$missing];
        sort($names);

        return $names;
    }
}
