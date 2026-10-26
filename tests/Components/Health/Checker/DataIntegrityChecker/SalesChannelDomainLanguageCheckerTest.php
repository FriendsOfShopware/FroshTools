<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\DataIntegrityChecker;

use Frosh\Tools\Components\Health\Checker\DataIntegrityChecker\SalesChannelDomainLanguageChecker;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(SalesChannelDomainLanguageChecker::class)]
class SalesChannelDomainLanguageCheckerTest extends DataIntegrityCheckerTestCase
{
    private const ID = 'sales-channel-domain-language-unassigned';

    private SalesChannelDomainLanguageChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->checker = static::getContainer()->get(SalesChannelDomainLanguageChecker::class);
        $this->connection->executeStatement(
            'INSERT IGNORE INTO sales_channel_language (sales_channel_id, language_id) SELECT sales_channel_id, language_id FROM sales_channel_domain'
        );
    }

    public function testReportsOkWhenDomainLanguagesAreAssigned(): void
    {
        $this->createDomain(Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM));
        $this->connection->executeStatement(
            'INSERT IGNORE INTO sales_channel_language (sales_channel_id, language_id) SELECT sales_channel_id, language_id FROM sales_channel_domain'
        );

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 0);
        static::assertAffectedEntities($this->checker, 'sales_channel', []);
    }

    public function testCountsDomainsWithAnUnassignedLanguage(): void
    {
        $salesChannelId = $this->createDomain($this->createLanguage());

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 1);
        static::assertAffectedEntities($this->checker, 'sales_channel', [$salesChannelId]);
    }

    public function testListsASalesChannelWithSeveralBrokenDomainsOnce(): void
    {
        $languageId = $this->createLanguage();
        $salesChannelId = $this->createDomain($languageId);
        $this->createDomain($languageId);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 2);
        static::assertAffectedEntities($this->checker, 'sales_channel', [$salesChannelId]);
    }

    private function createLanguage(): string
    {
        $id = Uuid::randomBytes();

        $this->connection->insert('language', [
            'id' => $id,
            'name' => 'Frosh data integrity check language',
            'locale_id' => $this->connection->fetchOne('SELECT id FROM locale LIMIT 1'),
            'created_at' => self::now(),
        ]);

        return $id;
    }

    private function createDomain(string $languageId): string
    {
        $salesChannelId = (string) $this->connection->fetchOne('SELECT id FROM sales_channel ORDER BY id LIMIT 1');

        $this->connection->insert('sales_channel_domain', [
            'id' => Uuid::randomBytes(),
            'sales_channel_id' => $salesChannelId,
            'language_id' => $languageId,
            'url' => 'https://frosh-data-integrity-check-' . Uuid::randomHex() . '.test',
            'currency_id' => Uuid::fromHexToBytes(Defaults::CURRENCY),
            'snippet_set_id' => $this->connection->fetchOne('SELECT id FROM snippet_set LIMIT 1'),
            'created_at' => self::now(),
        ]);

        return Uuid::fromBytesToHex($salesChannelId);
    }
}
