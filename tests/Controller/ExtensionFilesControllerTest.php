<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Controller;

use Frosh\Tools\Components\ExtensionChecksum\ExtensionFileHashService;
use Frosh\Tools\Components\ExtensionChecksum\Struct\ExtensionChecksumCheckResult;
use Frosh\Tools\Controller\ExtensionFilesController;
use Frosh\Tools\Tests\IntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(ExtensionFilesController::class)]
#[CoversClass(ExtensionChecksumCheckResult::class)]
class ExtensionFilesControllerTest extends IntegrationTestCase
{
    private const RESULT_FIELDS = [
        'fileMissing',
        'wrongVersion',
        'wrongExtensionVersion',
        'checkFailed',
        'newFiles',
        'changedFiles',
        'missingFiles',
    ];

    public function testListExtensionFilesReturnsSuccessAndStructuredResults(): void
    {
        $controller = static::getContainer()->get(ExtensionFilesController::class);

        $response = $controller->listExtensionFiles(Context::createDefaultContext());

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertIsArray($data);
        static::assertArrayHasKey('success', $data);
        static::assertIsBool($data['success']);
        static::assertArrayHasKey('extensionResults', $data);
        static::assertIsArray($data['extensionResults']);

        // The controller only lists extensions whose check result is not OK,
        // so success is true exactly when there are no results.
        static::assertSame($data['extensionResults'] === [], $data['success']);

        foreach ($data['extensionResults'] as $extensionName => $result) {
            static::assertIsString($extensionName);
            static::assertIsArray($result, sprintf('Result of extension "%s" should be an object', $extensionName));

            foreach (self::RESULT_FIELDS as $field) {
                static::assertArrayHasKey($field, $result, sprintf('Result of extension "%s" misses field "%s"', $extensionName, $field));
            }
        }

        $this->assertFroshToolsResultPresence($data['extensionResults']);
    }

    /**
     * FroshTools itself is installed by the test bootstrapper, but it only appears in
     * extensionResults when its checksum check is not OK. A missing checksum.json only
     * sets "fileMissing", which ExtensionChecksumCheckResult::isExtensionOk() ignores,
     * so presence depends on the environment and is asserted conditionally here.
     *
     * @param array<string, mixed> $extensionResults
     */
    private function assertFroshToolsResultPresence(array $extensionResults): void
    {
        $context = Context::createDefaultContext();

        $criteria = (new Criteria())->addFilter(new EqualsFilter('name', 'FroshTools'));
        $froshTools = static::getContainer()->get('plugin.repository')->search($criteria, $context)->getEntities()->first();

        static::assertInstanceOf(PluginEntity::class, $froshTools, 'FroshTools plugin should be installed by the test bootstrapper');

        try {
            $checkResult = static::getContainer()->get(ExtensionFileHashService::class)->checkExtensionForChanges($froshTools);
            $extensionOk = $checkResult->isExtensionOk();
        } catch (\Exception) {
            // The controller catches exceptions and reports the extension with checkFailed=true
            $extensionOk = false;
        }

        if ($extensionOk) {
            static::assertArrayNotHasKey('FroshTools', $extensionResults);
        } else {
            static::assertArrayHasKey('FroshTools', $extensionResults);

            foreach (self::RESULT_FIELDS as $field) {
                static::assertArrayHasKey($field, $extensionResults['FroshTools']);
            }
        }
    }
}
