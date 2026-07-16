<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Queue;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\VarDumper\Cloner\VarCloner;

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
            // get_object_vars() only returns public properties outside the message class.
            // VarCloner preserves protected and private message data without mutating it.
            return self::normalizeClonedObject($value, $depth);
        }

        return get_debug_type($value);
    }

    /**
     * @return array<string|int, mixed>
     */
    private static function normalizeClonedObject(object $value, int $depth): array
    {
        /** @var array<string|int, mixed> $properties */
        $properties = (new VarCloner())->cloneVar($value)->getValue(true);
        $result = [];

        foreach ($properties as $property => $propertyValue) {
            $name = self::normalizePropertyName($property);

            // A private property can share a name with a public or inherited one.
            // Preserve both values in that uncommon case.
            if (array_key_exists($name, $result)) {
                $name = (string) $property;
            }

            $result[$name] = self::normalize($propertyValue, $depth + 1);
        }

        return $result;
    }

    private static function normalizePropertyName(string|int $property): string|int
    {
        if (!\is_string($property) || !str_starts_with($property, "\0")) {
            return $property;
        }

        return substr($property, (int) strrpos($property, "\0") + 1);
    }
}
