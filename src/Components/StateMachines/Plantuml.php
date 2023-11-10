<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\StateMachines;

use Shopware\Core\System\StateMachine\StateMachineEntity;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class Plantuml implements ExportInterface
{
    private const DEFAULT_PATH = __DIR__ . '/../../Resources/views/administration/plantuml';

    private readonly Environment $twig;

    public function __construct()
    {
        $path = realpath(self::DEFAULT_PATH);
        if (!\is_string($path)) {
            throw new \RuntimeException('Could not find default path for PlantUML templates');
        }

        $this->twig = new Environment(new FilesystemLoader($path));
    }

    public function export(StateMachineEntity $stateMachine, string $title = ''): string
    {
        return $this->twig->render('state-machine.puml.twig', [
            'title' => $title,
            'initialState' => $stateMachine->getInitialState(),
            'states' => $stateMachine->getStates()?->getElements(),
            'transitions' => $stateMachine->getTransitions()?->getElements(),
        ]);
    }
}
