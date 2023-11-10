<?php

declare(strict_types=1);

namespace Frosh\Tools\Controller;

use Frosh\Tools\Components\Planuml;
use Frosh\Tools\Components\StateMachines\Mermaid;
use Frosh\Tools\Components\StateMachines\Plantuml;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\StateMachineEntity;

#[Route(path: '/api/_action/frosh-tools', defaults: ['_routeScope' => ['api'], '_acl' => ['frosh_tools:read']])]
final class StateMachineController extends AbstractController
{
    public function __construct(private readonly EntityRepository $stateMachineRepository) {}

    #[Route(path: '/state-machines/load/{stateMachineId}', name: 'api.frosh.tools.state-machines.load', methods: ['GET'])]
    public function load(string $stateMachineId, Request $request): JsonResponse
    {
        if (!Uuid::isValid($stateMachineId)) {
            return new JsonResponse();
        }

        $criteria = new Criteria([$stateMachineId]);
        $criteria->addAssociations([
            'states',
            'transitions',
        ]);

        $stateMachine = $this->stateMachineRepository->search($criteria, Context::createDefaultContext())->first();
        if (!$stateMachine instanceof StateMachineEntity) {
            return new JsonResponse();
        }

        $tmp = explode('.', $stateMachine->getTechnicalName());
        $title = ucwords(str_replace('_', ' ', $tmp[0]));

        $exporter = new Plantuml();
        $generatedPlantuml = $exporter->export($stateMachine, $title);

        $response = new JsonResponse();
        $encode = Planuml::encodep($generatedPlantuml);
        $response->setData(['svg' => '//www.plantuml.com/plantuml/svg/' . $encode]);

        return $response;
    }

    #[Route(path: '/state-machines/load-mermaid', name: 'api.frosh.tools.state-machines.load-mermaid', methods: ['GET'])]
    public function loadMermaid(Request $request): JsonResponse
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

        $exporter = new Mermaid();
        $generatedMermaid = $exporter->export($stateMachine, $title);

        $response = new JsonResponse($generatedMermaid);
        $response->setData(['data' => $generatedMermaid]);

        return $response;
    }
}
