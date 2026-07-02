<?php

declare(strict_types=1);

namespace Frosh\Tools\Controller;

use Frosh\Tools\Components\ExtensionChecksum\ExtensionFileHashService;
use Frosh\Tools\Components\ExtensionChecksum\Struct\ExtensionChecksumCheckResult;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Plugin\PluginCollection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[AutoconfigureTag('monolog.logger', ['channel' => 'frosh-tools'])]
#[Route(path: '/api/_action/frosh-tools', defaults: ['_routeScope' => ['api'], '_acl' => ['frosh_tools:read']])]
class ExtensionFilesController extends AbstractController
{
    /**
     * @param EntityRepository<PluginCollection> $pluginRepository
     */
    public function __construct(
        private readonly EntityRepository $pluginRepository,
        private readonly ExtensionFileHashService $pluginFileHashService,
        private readonly LoggerInterface $froshToolsLogger,
    ) {
    }

    #[Route(path: '/extension-files', name: 'api.frosh.tools.extension-files', methods: ['GET'])]
    public function listExtensionFiles(Context $context): JsonResponse
    {
        $extensionResults = [];

        /** @var PluginCollection $extensions */
        $extensions = $this->pluginRepository->search(new Criteria(), $context)->getEntities();
        foreach ($extensions as $extension) {
            try {
                $extensionChecksumCheckResult = $this->pluginFileHashService->checkExtensionForChanges($extension);
                if (!$extensionChecksumCheckResult->isExtensionOk()) {
                    $extensionResults[$extension->getName()] = $extensionChecksumCheckResult;
                }
            } catch (\Exception $exception) {
                $extensionResults[$extension->getName()] = new ExtensionChecksumCheckResult(checkFailed: true);
                $this->froshToolsLogger->error('Error checking checksums for extension {extension}: {message}', [
                    'extension' => $extension->getName(),
                    'message' => $exception->getMessage(),
                    'exception' => $exception,
                ]);
            }
        }

        return new JsonResponse([
            'success' => \count($extensionResults) === 0,
            'extensionResults' => $extensionResults,
        ]);
    }
}
