<?php
declare(strict_types=1);

namespace Frosh\Tools\Controller;

use Frosh\Tools\Components\Planuml;
use Frosh\Tools\Components\StateMachines\Plantuml;
use Frosh\Tools\FroshTools;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(path="/api/_action/frosh-tools", defaults={"_routeScope"={"api"}, "_acl"={"frosh_tools:read"}})
 */
final class StateMachineController
{
    private EntityRepository $stateMachineRepository;

    public function __construct(EntityRepository $stateMachineRepository)
    {
        $this->stateMachineRepository = $stateMachineRepository;
    }

    /**
     * @Route(path="/state-machines/load", methods={"GET"}, name="api.frosh.tools.state-machines.load")
     */
    public function load(Request $request): JsonResponse
    {
        $stateMachineType = $request->query->get('stateMachine');

        if (empty($stateMachineType)) {
            return new JsonResponse();
        }

        $tmp = explode('.', $stateMachineType);
        $title = ucwords(str_replace('_', ' ', $tmp[0]));

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', $stateMachineType));
        $criteria->addAssociations([
            'states',
            'transitions',
        ]);

        $stateMachine = $this->stateMachineRepository->search($criteria, Context::createDefaultContext())->first();

        $exporter = new Plantuml();
        $generatedPlantuml = $exporter->export($stateMachine, $title);

        $response = new JsonResponse();
        $encode = Planuml::encodep($generatedPlantuml);
        $response->setData(['svg' => '//www.plantuml.com/plantuml/svg/' . $encode]);

        return $response;
    }
}
