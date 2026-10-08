<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\DataIntegrityChecker;

use Frosh\Tools\Components\Health\Checker\DataIntegrityChecker\CategoryLinkTargetInvalidChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

#[CoversClass(CategoryLinkTargetInvalidChecker::class)]
class CategoryLinkTargetInvalidCheckerTest extends DataIntegrityCheckerTestCase
{
    private const ID = 'category-link-target-invalid';

    private CategoryLinkTargetInvalidChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->checker = static::getContainer()->get(CategoryLinkTargetInvalidChecker::class);
        $this->connection->executeStatement('UPDATE category SET type = :page WHERE type = :link', ['page' => 'page', 'link' => 'link']);
    }

    public function testReportsOkForLinksWithExistingTargets(): void
    {
        $languageId = $this->createLanguage();
        $landingPageId = $this->createLandingPage();

        $this->createCategory('link', [
            Defaults::LANGUAGE_SYSTEM => ['product', Uuid::fromHexToBytes($this->createProduct()), null],
            $languageId => ['product', null, null],
        ]);
        $this->createCategory('link', [Defaults::LANGUAGE_SYSTEM => ['category', $this->createCategory('page', []), null]]);
        $this->createCategory('link', [Defaults::LANGUAGE_SYSTEM => ['landing_page', $landingPageId, null]]);
        $this->createCategory('link', [Defaults::LANGUAGE_SYSTEM => ['external', null, 'https://example.com']]);
        $this->createCategory('link', [Defaults::LANGUAGE_SYSTEM => [null, null, 'https://example.com']]);
        $this->createCategory('page', [Defaults::LANGUAGE_SYSTEM => ['product', Uuid::randomBytes(), null]]);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 0);
    }

    public function testCountsLinksWithMissingTargets(): void
    {
        $languageId = $this->createLanguage();

        $this->createCategory('link', [Defaults::LANGUAGE_SYSTEM => ['product', Uuid::randomBytes(), null]]);
        $this->createCategory('link', [Defaults::LANGUAGE_SYSTEM => ['landing_page', Uuid::randomBytes(), null]]);
        $this->createCategory('link', [Defaults::LANGUAGE_SYSTEM => ['category', null, null]]);
        $this->createCategory('link', [Defaults::LANGUAGE_SYSTEM => ['external', null, '']]);
        $this->createCategory('link', [
            Defaults::LANGUAGE_SYSTEM => ['external', null, 'https://example.com'],
            $languageId => ['category', Uuid::randomBytes(), null],
        ]);
        $this->createCategory('link', [$languageId => ['external', null, 'https://example.com']]);
        $this->createCategory('link', [
            Defaults::LANGUAGE_SYSTEM => ['external', null, 'https://example.com'],
            $languageId => ['external', null, ''],
        ]);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 7);
    }

    /**
     * @param array<string, array{?string, ?string, ?string}> $translations
     */
    private function createCategory(string $type, array $translations): string
    {
        $id = Uuid::randomBytes();
        $liveVersionId = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);

        $this->connection->insert('category', [
            'id' => $id,
            'version_id' => $liveVersionId,
            'type' => $type,
            'created_at' => self::now(),
        ]);

        foreach ($translations as $languageId => [$linkType, $internalLink, $externalLink]) {
            $this->connection->insert('category_translation', [
                'category_id' => $id,
                'category_version_id' => $liveVersionId,
                'language_id' => Uuid::fromHexToBytes($languageId),
                'name' => 'Frosh data integrity check category',
                'link_type' => $linkType,
                'internal_link' => $internalLink,
                'external_link' => $externalLink,
                'created_at' => self::now(),
            ]);
        }

        return $id;
    }

    private function createLanguage(): string
    {
        $id = Uuid::randomHex();
        $localeId = $this->connection->fetchOne('SELECT id FROM locale LIMIT 1');

        $this->connection->insert('language', [
            'id' => Uuid::fromHexToBytes($id),
            'name' => 'Frosh',
            'locale_id' => $localeId,
            'translation_code_id' => $localeId,
            'created_at' => self::now(),
        ]);

        return $id;
    }

    private function createLandingPage(): string
    {
        $id = Uuid::randomBytes();

        $this->connection->insert('landing_page', [
            'id' => $id,
            'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'created_at' => self::now(),
        ]);

        return $id;
    }
}
