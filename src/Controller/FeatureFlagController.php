<?php declare(strict_types=1);

namespace Frosh\Tools\Controller;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Exception\InvalidRequestParameterException;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"api"})
 * @Route(path="/api/_action/frosh-tools")
 */
class FeatureFlagController
{
    private string $projectDir;

    public function __construct(string $projectDir)
    {
        $this->projectDir = $projectDir;
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

        return new JsonResponse(array_values($featureFlags));
    }

    /**
     * @Route(path="/feature-flag/toggle", methods={"POST"}, name="api.frosh.tools.feature-flag.toggle")
     */
    public function toggle(Request $request): Response
    {
        $featureFlag = $request->get('flag');

        if (!file_exists($this->projectDir . '/.env')) {
            throw new HttpException(
                Response::HTTP_INTERNAL_SERVER_ERROR,
                sprintf('File at %s does not exist', $this->projectDir . '/.env')
            );
        }

        if (empty($featureFlag)) {
            throw new MissingRequestParameterException('flag');
        }

        if (!Feature::has($featureFlag)) {
            throw new InvalidRequestParameterException('flag');
        }

        $dotEnvParsed = (new Dotenv())->parse(file_get_contents($this->projectDir . '/.env'));

        if (Feature::isActive($featureFlag)) {
            $dotEnvParsed[$featureFlag] = 0;
        } else {
            $dotEnvParsed[$featureFlag] = 1;
        }

        $this->updateEnvFile($dotEnvParsed);

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    private function updateEnvFile(array $configuration): void
    {
        $envVars = '';
        $envFile = $this->projectDir . '/.env';

        foreach ($configuration as $key => $value) {
            $envVars .= $key . '=' . $value . \PHP_EOL;
        }

        file_put_contents($envFile, $envVars);
    }
}
