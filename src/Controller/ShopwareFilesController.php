<?php
declare(strict_types=1);

namespace Frosh\Tools\Controller;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Kernel;
use Shopware\Core\System\User\UserEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/api/_action/frosh-tools', defaults: ['_routeScope' => ['api'], '_acl' => ['frosh_tools:read']])]
class ShopwareFilesController extends AbstractController
{
    private const STATUS_OK = 0;
    private const STATUS_IGNORED_ALL = 1;
    private const STATUS_IGNORED_IN_PROJECT = 2;

    private readonly bool $isPlatform;

    public function __construct(
        #[Autowire('%kernel.shopware_version%')] private readonly string $shopwareVersion,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
        #[Autowire('%frosh_tools.file_checker.exclude_files%')] private readonly array $projectExcludeFiles,
        #[Autowire(service: 'frosh_tools.logger')] private readonly LoggerInterface $froshToolsLogger,
        private readonly EntityRepository $userRepository
    ) {
        $this->isPlatform = !is_dir($this->projectDir . '/vendor/shopware/core') && is_dir($this->projectDir . '/src/Core');
    }

    #[Route(path: '/shopware-files', name: 'api.frosh.tools.shopware-files', methods: ['GET'])]
    public function listShopwareFiles(): JsonResponse
    {
        if ($this->shopwareVersion === Kernel::SHOPWARE_FALLBACK_VERSION) {
            return new JsonResponse(['error' => 'Git version is not supported']);
        }

        $url = sprintf('https://swagger.docs.fos.gg/version/%s/Files.xxhsums', $this->shopwareVersion);

        $data = trim(@file_get_contents($url));

        if (empty($data)) {
            return new JsonResponse(['error' => 'No file information for this Shopware version']);
        }

        $invalidFiles = [];
        $allFilesAreOkay = true;

        foreach (explode("\n", $data) as $row) {
            if ($this->isPlatform) {
                $row = preg_replace_callback('/vendor\/shopware\/(.)/', fn ($matches): string => 'src/' . strtoupper($matches[1]), $row);
            }

            [$expectedMd5Sum, $file] = explode('  ', trim($row));

            $path = $this->projectDir . '/' . $file;

            if (!is_file($path)) {
                continue;
            }

            $xxhSum = hash_file('xxh64', $path);

            $ignoredState = $this->isIgnoredFileHash($file);
            if ($ignoredState === self::STATUS_IGNORED_ALL) {
                continue;
            }

            if ($xxhSum !== $expectedMd5Sum) {
                if ($ignoredState === self::STATUS_OK) {
                    $allFilesAreOkay = false;
                }

                $invalidFiles[] = [
                    'name' => $file,
                    'shopwareUrl' => $this->getShopwareUrl($file),
                    'expected' => $ignoredState === self::STATUS_IGNORED_IN_PROJECT,
                ];
            }

            // WE SHOULD STOP HERE, WHILE THERE MIGHT BE ANY BIG PROBLEM!
            if (\count($invalidFiles) > 100) {
                break;
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
    public function restoreShopwareFile(Request $request, Context $context): JsonResponse
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

        $userName = $this->getUserName($context) ?? 'unknown';

        $message = sprintf('File at "%s" has been restored by %s', $file, $userName);

        $this->froshToolsLogger->info($message);

        return new JsonResponse(['status' => $message]);
    }

    private function getShopwareUrl(string $name): ?string
    {
        $name = $this->isPlatform ? preg_replace('/^src\//', '', $name) : preg_replace('/^vendor\/shopware\//', '', $name);

        $pathParts = \explode('/', $name);
        $repo = $pathParts[0];
        array_shift($pathParts);

        return 'https://github.com/shopware/'
            . $repo
            . '/blob/v'
            . $this->shopwareVersion
            . '/'
            . \implode('/', $pathParts);
    }

    private function getOriginalFileContent(string $name): ?string
    {
        return @file_get_contents($this->getShopwareUrl($name) . '?raw=true') ?: null;
    }

    private function isIgnoredFileHash(string $file): int
    {
        if (\in_array($file, $this->projectExcludeFiles, true)) {
            return self::STATUS_IGNORED_IN_PROJECT;
        }

        return self::STATUS_OK;
    }

    private function getUserName(Context $context): ?string
    {
        $contextSource = $context->getSource();

        if (!$contextSource instanceof AdminApiSource) {
            return null;
        }

        /** @var null|UserEntity $userEntity */
        $userEntity = $this->userRepository->search(new Criteria([$contextSource->getUserId()]), Context::createDefaultContext())->first();

        if (!$userEntity instanceof UserEntity) {
            return null;
        }

        return $userEntity->getUsername();
    }
}
