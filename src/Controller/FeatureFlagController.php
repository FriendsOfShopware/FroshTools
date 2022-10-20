<?php declare(strict_types=1);

namespace Frosh\Tools\Controller;

use Frosh\Tools\Components\Environment\EnvironmentManager;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Routing\Exception\InvalidRequestParameterException;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(path="/api/_action/frosh-tools", defaults={"_routeScope"={"api"}, "_acl"={"frosh_tools:read"}})
 */
class FeatureFlagController
{
    private string $envPath;

    public function __construct(string $projectDir)
    {
        $this->envPath = $projectDir . '/.env';
    }

    /**
     * @Route(path="/feature-flag/list", methods={"GET"}, name="api.frosh.tools.feature-flag.list")
     */
    public function list(): JsonResponse
    {
        $featureFlags = Feature::getRegisteredFeatures();

        foreach (array_keys($featureFlags) as $featureFlag) {
            $featureFlags[$featureFlag]['flag'] = $featureFlag;
            $featureFlags[$featureFlag]['active'] = Feature::isActive($featureFlag);
        }

        $activeColumns = array_column($featureFlags, 'active');
        $flagColumns = array_column($featureFlags, 'flag');
        array_multisort($activeColumns, \SORT_DESC,
            $flagColumns, \SORT_ASC,
            $featureFlags);

        return new JsonResponse(array_values($featureFlags));
    }

    /**
     * @Route(path="/feature-flag/toggle", methods={"POST"}, name="api.frosh.tools.feature-flag.toggle")
     */
    public function toggle(Request $request): Response
    {
        $featureFlag = $request->get('flag');

        if (!file_exists($this->envPath)) {
            throw new HttpException(
                Response::HTTP_INTERNAL_SERVER_ERROR,
                sprintf('File at %s does not exist', $this->envPath)
            );
        }

        if (empty($featureFlag)) {
            throw new MissingRequestParameterException('flag');
        }

        if (!Feature::has($featureFlag)) {
            throw new InvalidRequestParameterException('flag');
        }

        $this->updateEnvFile($featureFlag);

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    private function updateEnvFile(string $featureFlag): void
    {
        $manager = new EnvironmentManager();
        $file = $manager->read($this->envPath);

        if (Feature::isActive($featureFlag)) {
            $file->set($featureFlag, '0');
        } else {
            $file->set($featureFlag, '1');
        }

        $manager->save($this->envPath, $file);
    }
}
