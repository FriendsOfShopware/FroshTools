<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Queue;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;

class QueueMessage implements \JsonSerializable
{
    private const MAX_DEPTH = 6;

    /**
     * @param array<string> $stamps
     */
    public function __construct(
        public readonly ?string $id,
        public readonly string $messageClass,
        public readonly int $retryCount,
        public readonly mixed $body,
        public readonly array $stamps = [],
        public readonly ?string $errorMessage = null,
        public readonly ?string $originalTransport = null,
    ) {
    }

    public static function fromEnvelope(Envelope $envelope, ?string $id = null): self
    {
        $message = $envelope->getMessage();

        if ($id === null) {
            $idStamp = $envelope->last(TransportMessageIdStamp::class);
            if ($idStamp !== null) {
                $id = (string) $idStamp->getId();
            }
        }

        $redeliveryStamp = $envelope->last(RedeliveryStamp::class);
        $errorStamp = $envelope->last(ErrorDetailsStamp::class);
        $failureStamp = $envelope->last(SentToFailureTransportStamp::class);

        $stamps = [];
        foreach ($envelope->all() as $stampClass => $items) {
            $parts = explode('\\', $stampClass);
            $stamps[] = end($parts) . (\count($items) > 1 ? ' ×' . \count($items) : '');
        }

        return new self(
            $id,
            $message::class,
            $redeliveryStamp?->getRetryCount() ?? 0,
            self::normalize($message),
            $stamps,
            $errorStamp !== null ? $errorStamp->getExceptionClass() . ': ' . $errorStamp->getExceptionMessage() : null,
            $failureStamp?->getOriginalReceiverName(),
        );
    }

    public static function raw(?string $id, string $body): self
    {
        return new self($id, 'unknown', 0, $body);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'messageClass' => $this->messageClass,
            'retryCount' => $this->retryCount,
            'body' => $this->body,
            'stamps' => $this->stamps,
            'errorMessage' => $this->errorMessage,
            'originalTransport' => $this->originalTransport,
        ];
    }

    private static function normalize(mixed $value, int $depth = 0): mixed
    {
        if ($value === null || \is_scalar($value)) {
            return $value;
        }

        if ($depth >= self::MAX_DEPTH) {
            return '…';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DATE_ATOM);
        }

        if ($value instanceof \UnitEnum) {
            return $value instanceof \BackedEnum ? $value->value : $value->name;
        }

        if (\is_array($value)) {
            $result = [];
            foreach ($value as $key => $item) {
                $result[$key] = self::normalize($item, $depth + 1);
            }

            return $result;
        }

        if (\is_object($value)) {
            $result = [];
            $reflection = new \ReflectionObject($value);
            do {
                foreach ($reflection->getProperties() as $property) {
                    if ($property->isStatic() || \array_key_exists($property->getName(), $result)) {
                        continue;
                    }

                    if (!$property->isInitialized($value)) {
                        continue;
                    }

                    $result[$property->getName()] = self::normalize($property->getValue($value), $depth + 1);
                }
            } while (($reflection = $reflection->getParentClass()) !== false);

            return $result;
        }

        return get_debug_type($value);
    }
}
