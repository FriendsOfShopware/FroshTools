<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Telemetry;

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Minimal PostHog capture client for the EU cloud (https://eu.posthog.com).
 *
 * Uses the public project key (write-only) and the plain /capture/ endpoint
 * instead of pulling in the posthog-php SDK for a single event per day.
 */
class PostHogClient
{
    /**
     * TODO: replace with the Frosh PostHog EU project key (phc_...).
     * Until then TelemetryService treats telemetry as not configured and sends nothing.
     */
    public const DEFAULT_API_KEY = 'phc_REPLACE_WITH_PROJECT_KEY';
    public const DEFAULT_HOST = 'https://eu.i.posthog.com';
    public const EVENT = 'frosh_tools_telemetry';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $apiKey = self::DEFAULT_API_KEY,
        private readonly string $host = self::DEFAULT_HOST,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '' && $this->apiKey !== self::DEFAULT_API_KEY && str_starts_with($this->apiKey, 'phc_');
    }

    /**
     * @param array<string, bool|int|string> $properties
     *
     * @throws ExceptionInterface
     */
    public function capture(string $distinctId, array $properties, ?\DateTimeImmutable $timestamp = null): bool
    {
        $timestamp ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        $response = $this->httpClient->request('POST', rtrim($this->host, '/') . '/capture/', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'api_key' => $this->apiKey,
                'event' => self::EVENT,
                'distinct_id' => $distinctId,
                'timestamp' => $timestamp->format(\DateTimeInterface::ATOM),
                'properties' => [
                    // Anonymous event: create no person profile and let PostHog
                    // not derive GeoIP data from the request IP address.
                    '$process_person_profile' => false,
                    '$geoip_disable' => true,
                    ...$properties,
                ],
            ],
            'timeout' => 5,
        ]);

        return $response->getStatusCode() === 200;
    }
}
