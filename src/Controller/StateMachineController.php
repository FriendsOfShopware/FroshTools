<?php
declare(strict_types=1);
namespace Frosh\Tools\Controller;

use function Jawira\PlantUml\encodep;

use Frosh\Tools\Components\StateMachines\Plantuml;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;


/**
 * @Route(path="/api/_action/frosh-tools", defaults={"_routeScope"={"api"}})
 */
final class StateMachineController
{
    private EntityRepository $stateMachineRepository;
    
    private TranslatorInterface $translator;

    public function __construct(EntityRepository $stateMachineRepository, TranslatorInterface $translator)
    {
        $this->stateMachineRepository = $stateMachineRepository;
        $this->translator = $translator;
    }

    /**
     * @Route(path="/state-machines/load", methods={"GET"}, name="api.frosh.tools.state-machines.load")
     */
    public function load(Request $request) : JsonResponse
    {
        $stateMachineType = $request->query->get('stateMachine');
        
        if (empty($stateMachineType)) {
            return new JsonResponse();
        }
        
        $tmp = explode('.',$stateMachineType);
        $title = ucwords(str_replace('_', ' ', $tmp[0]));

        /**
         * @var \Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria $criteria
         */
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
        $encode = encodep($generatedPlantuml);
        $response->setData(['svg' => '//www.plantuml.com/plantuml/svg/'.$encode]);
        return $response;
    }
}
