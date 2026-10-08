<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\DataIntegrityChecker;

use Frosh\Tools\Components\Health\Checker\DataIntegrityChecker\LanguageTranslationCodeMissingChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageLoader;
use Shopware\Core\System\Locale\LanguageLocaleCodeProvider;

#[CoversClass(LanguageTranslationCodeMissingChecker::class)]
class LanguageTranslationCodeMissingCheckerTest extends DataIntegrityCheckerTestCase
{
    private const ID = 'language-translation-code-missing';

    private LanguageTranslationCodeMissingChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->checker = static::getContainer()->get(LanguageTranslationCodeMissingChecker::class);
        $this->connection->executeStatement('UPDATE language SET translation_code_id = locale_id WHERE translation_code_id IS NULL');
    }

    public function testReportsOkWhenEveryLanguageResolvesATranslationCode(): void
    {
        $parentId = $this->createLanguage($this->localeId());
        $this->createLanguage(null, $parentId);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 0);
    }

    public function testCountsLanguagesWithoutOwnOrInheritedTranslationCode(): void
    {
        $parentId = $this->createLanguage($this->localeId());
        $this->createLanguage(null, $parentId);

        $parentWithoutCodeId = $this->createLanguage(null);
        $this->createLanguage(null, $parentWithoutCodeId);
        $this->createLanguage($this->localeId(), $parentWithoutCodeId);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 2);
    }

    public function testResolvingTheLocaleOfALanguageWithoutTranslationCodeFails(): void
    {
        $languageId = $this->createLanguage(null);

        $provider = new LanguageLocaleCodeProvider(new LanguageLoader($this->connection));

        $this->expectException(\TypeError::class);
        $provider->getLocaleForLanguageId(Uuid::fromBytesToHex($languageId));
    }

    private function localeId(): string
    {
        return $this->connection->fetchOne('SELECT id FROM locale LIMIT 1');
    }

    private function createLanguage(?string $translationCodeId, ?string $parentId = null): string
    {
        $id = Uuid::randomBytes();

        $this->connection->insert('language', [
            'id' => $id,
            'parent_id' => $parentId,
            'name' => 'Frosh data integrity check language',
            'locale_id' => $this->localeId(),
            'translation_code_id' => $translationCodeId,
            'created_at' => self::now(),
        ]);

        return $id;
    }
}
