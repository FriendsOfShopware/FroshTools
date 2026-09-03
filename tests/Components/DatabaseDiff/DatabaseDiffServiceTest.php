<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\DatabaseDiff;

use Frosh\Tools\Components\DatabaseDiff\DatabaseDiffService;
use Frosh\Tools\Components\DatabaseDiff\Swdb;
use Frosh\Tools\Tests\Components\DatabaseDiff\Swdb\ModelTest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Util\Json;
use Shopware\Core\Framework\Validation\DataValidator;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(DatabaseDiffService::class)]
class DatabaseDiffServiceTest extends TestCase
{
    use KernelTestBehaviour;

    protected const EXAMPLE_VERSION = ModelTest::VERSION_A;

    protected const JSON__SCHEMA_A  = ModelTest::JSON__SCHEMA_A;
    protected const JSON__SCHEMA_B  = ModelTest::JSON__SCHEMA_B;

    protected const JSON__AVAILABLE_VERSIONS = <<<'JSON'
[{
  "version": "6.6.10.0",
  "slug": "6_6_10_0",
  "major": 6,
  "minor": 6,
  "majorMinor": "6.6",
  "status": "available",
  "tableCount": 229,
  "url": "https://swdb.dev/version/6_6_10_0/",
  "schemaUrl": "https://swdb.dev/api/schemas/6_6_10_0.schema.json"
}, {
  "version": "6.7.13.0",
  "slug": "6_7_13_0",
  "major": 6,
  "minor": 7,
  "majorMinor": "6.7",
  "status": "available",
  "tableCount": 252,
  "url": "https://swdb.dev/version/6_7_13_0/",
  "schemaUrl": "https://swdb.dev/api/schemas/6_7_13_0.schema.json"
}]
JSON;

    public function testLoadAvailableVersions(): void
    {
        $versions = static::JSON__AVAILABLE_VERSIONS;

        $mockHttpClient = new MockHttpClient([
            new MockResponse($versions),
        ]);
        $dataValidator  = $this->getContainer()
            ->get(DataValidator::class);

        $service = new DatabaseDiffService(
            $mockHttpClient,
            $dataValidator,
        );

        self::assertSame(Json::decodeToList($versions),
            \array_values($service->getAvailableVersions())
        );
    }

    public function testLoadDatabaseSchema(): void
    {
        $version = static::EXAMPLE_VERSION;

        $mockHttpClient = new MockHttpClient([
            new MockResponse(static::JSON__SCHEMA_A),
        ]);
        $dataValidator  = $this->getContainer()
            ->get(DataValidator::class);

        $service = new DatabaseDiffService(
            $mockHttpClient,
            $dataValidator,
        );

        $schema = $service->getDatabaseSchema($version);

        self::assertInstanceOf(Swdb\Schema::class, $schema);
        self::assertCount(2, $schema->tables);
        self::assertSame($service->parseVersionSlug($version), $schema->version);
        self::assertSame(['cart', 'payment_token'], \array_keys($schema->tables));
    }

    #[DataProvider('provideVersions')]
    public function testParseVersionSlug(string $version, string $expectedSlug): void
    {
        $slug = $this->getContainer()
            ->get(DatabaseDiffService::class)
            ->parseVersionSlug($version);

        self::assertSame($expectedSlug, $slug);
    }

    public static function provideVersions(): iterable
    {
        yield 'normal version'            => ['6.6.10.0',  '6_6_10_0'];
        yield 'release candidate version' => ['6.1.0.rc1', '6_1_0_rc1'];
    }

    public function testCreateSchemaDiff(): void
    {
        $versionA = static::EXAMPLE_VERSION;
        $versionB = '6.6.10.0';

        $mockHttpClient = new MockHttpClient([
            new MockResponse(static::JSON__SCHEMA_A),
            new MockResponse(static::JSON__SCHEMA_B),
        ]);
        $dataValidator  = $this->getContainer()
            ->get(DataValidator::class);

        $service = new DatabaseDiffService(
            $mockHttpClient,
            $dataValidator,
        );

        $schemaA = $service->getDatabaseSchema($versionA);
        $schemaB = $service->getDatabaseSchema($versionB);

        $diff = $this->getContainer()
            ->get(DatabaseDiffService::class)
            ->createSchemaDiff($schemaA, $schemaB);

        self::assertNotEmpty($diff);
        self::assertEquals([
            'table'  => 'payment_token',
            'type'   => 'fields',
            'label'  => 'removed',
            'name'   => 'payment_token.consumed',
            'valueA' => 'tinyint(1) null default "0"',
            'valueB' => null,
        ], $diff[0]);
    }
}
