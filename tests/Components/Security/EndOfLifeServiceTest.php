<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Security;

use Frosh\Tools\Components\Security\EndOfLifeService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(EndOfLifeService::class)]
class EndOfLifeServiceTest extends TestCase
{
    #[DataProvider('supportProvider')]
    public function testSupportEnded(bool|string|null $support, bool $expectedSupportEnded): void
    {
        $entry = ['cycle' => '9.7', 'eol' => '2034-04-21', 'latest' => '9.7.2'];
        if ($support !== null) {
            $entry['support'] = $support;
        }

        $cycle = $this->getCycle($entry);

        static::assertNotNull($cycle);
        static::assertSame($expectedSupportEnded, $cycle['supportEnded']);
    }

    public function testApiErrorYieldsNull(): void
    {
        $client = new MockHttpClient([new MockResponse('', ['http_code' => 500])]);
        $service = new EndOfLifeService($client, new ArrayAdapter());

        static::assertNull($service->getCycle('mysql', '9.7.2'));
    }

    public function testUnknownCycleYieldsNull(): void
    {
        static::assertNull($this->getCycle(['cycle' => '8.0', 'eol' => '2026-04-30']), 'requested cycle 9.7 is not in the payload');
    }

    public static function supportProvider(): iterable
    {
        yield 'boolean true means active support' => [true, false];
        yield 'boolean false means active support has ended' => [false, true];
        yield 'past date means active support has ended' => ['2020-01-01', true];
        yield 'future date means active support' => ['2099-01-01', false];
        yield 'unparseable date stays not ended' => ['not-a-date', false];
        yield 'absent field stays not ended' => [null, false];
    }

    /**
     * @param array<string, mixed> $entry
     *
     * @return array{cycle: string, eol: \DateTimeImmutable|null, eolUnknown: bool, supportEnded: bool, latest: string|null}|null
     */
    private function getCycle(array $entry): ?array
    {
        $client = new MockHttpClient([new MockResponse(json_encode([$entry], \JSON_THROW_ON_ERROR))]);
        $service = new EndOfLifeService($client, new ArrayAdapter());

        return $service->getCycle('mysql', '9.7.2');
    }
}
