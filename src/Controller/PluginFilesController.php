<?php

declare(strict_types=1);

namespace Frosh\Tools\Controller;

use Frosh\Tools\Components\PluginChecksum\PluginFileHashService;
use Frosh\Tools\Components\PluginChecksum\Struct\PluginChecksumCheckResult;
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
class PluginFilesController extends AbstractController
{
    /**
     * @param EntityRepository<PluginCollection> $pluginRepository
     */
    public function __construct(
        private readonly EntityRepository $pluginRepository,
        private readonly PluginFileHashService $pluginFileHashService,
        private readonly LoggerInterface $froshToolsLogger,
    ) {
    }

    #[Route(path: '/plugin-files', name: 'api.frosh.tools.plugin-files', methods: ['GET'])]
    public function listPluginFiles(Context $context): JsonResponse
    {
        $pluginResults = [];

        /** @var PluginCollection $plugins */
        $plugins = $this->pluginRepository->search(new Criteria(), $context)->getEntities();
        foreach ($plugins as $plugin) {
            try {
                $pluginChecksumCheckResult = $this->pluginFileHashService->checkPluginForChanges($plugin);
                if (!$pluginChecksumCheckResult->isPluginOk()) {
                    $pluginResults[$plugin->getName()] = $pluginChecksumCheckResult;
                }
            } catch (\Exception $exception) {
                $pluginResults[$plugin->getName()] = new PluginChecksumCheckResult(checkFailed: true);
                $this->froshToolsLogger->error('Error checking checksums for plugin {plugin}: {message}', [
                    'plugin' => $plugin->getName(),
                    'message' => $exception->getMessage(),
                    'exception' => $exception,
                ]);
            }
        }

        return new JsonResponse([
            'success' => \count($pluginResults) === 0,
            'pluginResults' => $pluginResults,
        ]);
    }
}
