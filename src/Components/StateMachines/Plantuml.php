<?php
declare(strict_types=1);

namespace Frosh\Tools\Components\StateMachines;

use Shopware\Core\System\StateMachine\StateMachineEntity;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class Plantuml implements ExportInterface
{
    private Environment $twig;

    private const DEFAULT_PATH = __DIR__ . '/../../Resources/views/administration/plantuml';

    public function __construct()
    {
        $path = realpath(self::DEFAULT_PATH);
        $this->twig = new Environment(new FilesystemLoader($path));
    }

    /**
     * {@inheritDoc}
     *
     * @see \Frosh\Tools\Components\StateMachines\ExportInterface::export()
     */
    public function export(StateMachineEntity $stateMachine, string $title = ''): string
    {
        return $this->twig->render('state-machine.puml.twig', [
            'title' => $title,
            'initialState' => $stateMachine->getInitialState(),
            'states' => $stateMachine->getStates()
                ->getElements(),
            'transitions' => $stateMachine->getTransitions()
                ->getElements(),
        ]);
    }
}
