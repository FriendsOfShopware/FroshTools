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
    }

    public function testCountsDomainsWithAnUnassignedLanguage(): void
    {
        $languageId = Uuid::randomBytes();
        $this->connection->insert('language', [
            'id' => $languageId,
            'name' => 'Frosh data integrity check language',
            'locale_id' => $this->connection->fetchOne('SELECT id FROM locale LIMIT 1'),
            'created_at' => self::now(),
        ]);

        $this->createDomain($languageId);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 1);
    }

    private function createDomain(string $languageId): void
    {
        $this->connection->insert('sales_channel_domain', [
            'id' => Uuid::randomBytes(),
            'sales_channel_id' => $this->connection->fetchOne('SELECT id FROM sales_channel LIMIT 1'),
            'language_id' => $languageId,
            'url' => 'https://frosh-data-integrity-check-' . Uuid::randomHex() . '.test',
            'currency_id' => Uuid::fromHexToBytes(Defaults::CURRENCY),
            'snippet_set_id' => $this->connection->fetchOne('SELECT id FROM snippet_set LIMIT 1'),
            'created_at' => self::now(),
        ]);
    }
}
