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
use Shopware\Core\System\StateMachine\StateMachineCollection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\StateMachineEntity;

#[Route(path: '/api/_action/frosh-tools', defaults: ['_routeScope' => ['api'], '_acl' => ['frosh_tools:read']])]
final class StateMachineController extends AbstractController
{
    /**
     * @param EntityRepository<StateMachineCollection> $stateMachineRepository
     */
    public function __construct(private readonly EntityRepository $stateMachineRepository) {}

    #[Route(path: '/state-machines/load/{stateMachineId}', name: 'api.frosh.tools.state-machines.load', methods: ['GET'])]
    public function load(string $stateMachineId, Context $context): JsonResponse
    {
        if (!Uuid::isValid($stateMachineId)) {
            return new JsonResponse();
        }

        $criteria = new Criteria([$stateMachineId]);
        $criteria->addAssociations([
            'states',
            'transitions',
        ]);

        $stateMachine = $this->stateMachineRepository->search($criteria, $context)->first();
        if (!$stateMachine instanceof StateMachineEntity) {
            return new JsonResponse();
        }

        $tmp = explode('.', $stateMachine->getTechnicalName());
        $title = ucwords(str_replace('_', ' ', $tmp[0]));

        $exporter = new Plantuml();
        $generatedPlantuml = $exporter->export($stateMachine, $title);

        $encode = Planuml::encodep($generatedPlantuml);

        return new JsonResponse(['svg' => '//www.plantuml.com/plantuml/svg/' . $encode]);
    }

    #[Route(path: '/state-machines/load-mermaid', name: 'api.frosh.tools.state-machines.load-mermaid', methods: ['GET'])]
    public function loadMermaid(Request $request, Context $context): JsonResponse
    {
        $stateMachineType = $request->query->getString('stateMachine');

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

        $stateMachine = $this->stateMachineRepository->search($criteria, $context)->getEntities()->first();

        \assert($stateMachine instanceof StateMachineEntity);

        $exporter = new Mermaid();
        $generatedMermaid = $exporter->export($stateMachine, $title);

        return new JsonResponse(['data' => $generatedMermaid]);
    }
}
