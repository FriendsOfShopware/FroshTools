<?php declare(strict_types=1);

namespace Frosh\Tools\Controller;

use Shopware\Core\Kernel;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/api/_action/frosh-tools', defaults: ['_routeScope' => ['api'], '_acl' => ['frosh_tools:read']])]
class ShopwareFilesController
{
    private const STATUS_OK = 0;
    private const STATUS_IGNORED_ALL = 1;
    private const STATUS_IGNORED_IN_PROJECT = 2;
    private bool $isPlatform;

    public function __construct(
        private readonly string $shopwareVersion,
        private readonly string $projectDir,
        private readonly array $projectIgnoredFiles
    )
    {
        $this->isPlatform = !is_dir($this->projectDir . '/vendor/shopware/core') && is_dir($this->projectDir . '/src/Core');
    }

    #[Route(path: '/shopware-files', name: 'api.frosh.tools.shopware-files', methods: ['GET'])]
    public function listShopwareFiles(): JsonResponse
    {
        if ($this->shopwareVersion === Kernel::SHOPWARE_FALLBACK_VERSION) {
            return new JsonResponse(['error' => 'Git version is not supported']);
        }

        $url = sprintf('https://swagger.docs.fos.gg/version/%s/Files.md5sums', $this->shopwareVersion);

        $data = trim(@file_get_contents($url));

        if (empty($data)) {
            return new JsonResponse(['error' => 'No file information for this Shopware version']);
        }

        $invalidFiles = [];
        $allFilesAreOkay = true;

        foreach (explode("\n", $data) as $row) {
            if ($this->isPlatform) {
                $row = preg_replace_callback('/vendor\/shopware\/(.)/', function ($matches) {
                    return 'src/' . strtoupper($matches[1]);
                }, $row);
            }

            [$expectedMd5Sum, $file] = explode('  ', trim($row));

            $path = $this->projectDir . '/' . $file;

            if (!is_file($path)) {
                continue;
            }

            $md5Sum = md5_file($path);

            $ignoredState = $this->isIgnoredFileHash($file, $md5Sum);
            if ($ignoredState === self::STATUS_IGNORED_ALL) {
                continue;
            }

            if ($md5Sum !== $expectedMd5Sum) {
                if ($ignoredState === self::STATUS_OK) {
                    $allFilesAreOkay = false;
                }

                $invalidFiles[] = [
                    'name' => $file,
                    'shopwareUrl' => $this->getShopwareUrl($file),
                    'expected' => $ignoredState === self::STATUS_IGNORED_IN_PROJECT,
                ];
            }
        }

        if ($allFilesAreOkay) {
            return new JsonResponse(['ok' => true, 'files' => $invalidFiles]);
        }

        return new JsonResponse(['ok' => false, 'files' => $invalidFiles]);
    }

    #[Route(path: '/file-contents', name: 'api.frosh.tools.file-contents', methods: ['GET'])]
    public function getFileContents(Request $request): JsonResponse
    {
        if ($this->shopwareVersion === Kernel::SHOPWARE_FALLBACK_VERSION) {
            return new JsonResponse(['error' => 'Git version is not supported']);
        }

        $file = $request->query->get('file');
        if (!$file) {
            return new JsonResponse(['error' => 'no file provided']);
        }

        $path = realpath($this->projectDir . '/' . $file);
        if ($path === false || !str_starts_with($path, $this->projectDir) || !is_file($path)) {
            return new JsonResponse(['error' => 'File is invalid']);
        }

        return new JsonResponse([
            'name' => $file,
            'shopwareUrl' => $this->getShopwareUrl($file),
            'content' => file_get_contents($path),
            'originalContent' => $this->getOriginalFileContent($file),
        ]);
    }

    #[Route(path: '/shopware-file/restore', name: 'api.frosh.tools.shopware-file.restore', methods: ['GET'])]
    public function restoreShopwareFile(Request $request): JsonResponse
    {
        if ($this->shopwareVersion === Kernel::SHOPWARE_FALLBACK_VERSION) {
            return new JsonResponse(['error' => 'Git version is not supported']);
        }

        $file = $request->query->get('file');
        if (!$file) {
            return new JsonResponse(['error' => 'no file provided']);
        }

        $path = realpath($this->projectDir . '/' . $file);
        if ($path === false || !str_starts_with($path, $this->projectDir) || !is_file($path)) {
            return new JsonResponse(['error' => 'File is invalid']);
        }

        $content = $this->getOriginalFileContent($file);
        if ($content === null || $content === '') {
            return new JsonResponse(['error' => 'File would be empty!']);
        }

        file_put_contents($path, $content);

        if (\function_exists('opcache_reset')) {
            opcache_reset();
        }

        return new JsonResponse(['status' => sprintf('File at "%s" has been restored', $file)]);
    }

    private function getShopwareUrl(string $name): ?string
    {
        if ($this->isPlatform) {
            $name = preg_replace('/^src\//', '', $name);
        } else {
            $name = preg_replace('/^vendor\/shopware\//', '', $name);
        }

        $pathParts = \explode('/', $name);
        $repo = $pathParts[0];
        array_shift($pathParts);

        return 'https://github.com/shopware/' .
            $repo .
            '/blob/v' .
            $this->shopwareVersion .
            '/' .
            \implode('/', $pathParts);
    }

    private function getOriginalFileContent(string $name): ?string
    {
        return @file_get_contents($this->getShopwareUrl($name) . '?raw=true') ?: null;
    }

    private function isIgnoredFileHash(string $file, string $md5Sum): int
    {
        // This file differs on update systems. This change is missing in update packages lol!
        // @see: https://github.com/shopware/platform/commit/957e605c96feef67a6c759f00c58e35d2d1ac84f#diff-e49288a50f0d7d8acdabb5ffef2edcd5ac4f4126f764d3153d19913ce98aba1cL10-R80
        // @see: https://issues.shopware.com/issues/NEXT-11618
        if ($file === 'vendor/shopware/core/Checkout/Order/Aggregate/OrderAddress/OrderAddressDefinition.php'
            && $md5Sum === 'e3da59baff091fd044a12a61cd445385') {
            return self::STATUS_IGNORED_ALL;
        }

        // This file differs on update systems. This change is missing in update packages lol!
        // @see: https://github.com/shopware/platform/commit/bbdcbe254e3239e92eb1f71a7afedfb94b7fb150
        // @see: https://issues.shopware.com/issues/NEXT-11775
        if ($file === 'vendor/shopware/administration/Resources/app/administration/src/app/component/media/sw-media-compact-upload-v2/index.js'
            && $md5Sum === '74d18e580ffe87559e6501627090efb3') {
            return self::STATUS_IGNORED_ALL;
        }

        if (in_array($file, $this->projectIgnoredFiles, true)) {
            return self::STATUS_IGNORED_IN_PROJECT;
        }

        return self::STATUS_OK;
    }

    private function assertNoGitVersion(): ?JsonResponse
    {
        if ($this->shopwareVersion === Kernel::SHOPWARE_FALLBACK_VERSION) {
            return new JsonResponse(['error' => 'Git version is not supported']);
        }

        return null;
    }
}
