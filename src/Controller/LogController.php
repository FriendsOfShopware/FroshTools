<?php

declare(strict_types=1);

namespace Frosh\Tools\Controller;

use Frosh\Tools\Components\LineReader;
use Shopware\Core\Framework\Routing\RoutingException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/api/_action/frosh-tools', defaults: ['_routeScope' => ['api'], '_acl' => ['frosh_tools:read']])]
class LogController extends AbstractController
{
    // https://regex101.com/r/bp4YYL/1
    private const LINE_MATCH = '/\[(?<date>.*)] (?<channel>.*)\.(?<level>(DEBUG|INFO|NOTICE|WARNING|ERROR|CRITICAL|ALERT|EMERGENCY)):(?<message>.*)/m';

    private readonly string $logDir;

    public function __construct(
        #[Autowire('%kernel.logs_dir%')]
        string $logDir
    ) {
        $this->logDir = rtrim($logDir, '/') . '/';
    }

    #[Route(path: '/logs/files', name: 'api.frosh.tools.logs.files', methods: ['GET'])]
    public function getLogFiles(): JsonResponse
    {
        return new JsonResponse($this->getFiles());
    }

    #[Route(path: '/logs/file', name: 'api.frosh.tools.logs.file-listing', methods: ['GET'])]
    public function getLog(Request $request): Response
    {
        $filePath = $this->getFilePathByBag($request);
        $offset = $request->query->getInt('offset');
        $limit = $request->query->getInt('limit', 20);

        $lineGenerator = LineReader::readLinesBackwards($filePath);
        $file = new \SplFileObject($filePath, 'r');
        $file->seek(\PHP_INT_MAX);

        $reader = new \LimitIterator($lineGenerator, $offset, $limit);

        $result = [];

        /** @var string $item */
        foreach ($reader as $item) {
            if (preg_match(self::LINE_MATCH, $item, $matches) === false) {
                $result[] = [
                    'message' => $item,
                    'channel' => 'unknown',
                    'date' => 'unknown',
                    'level' => 'unknown',
                ];

                continue;
            }

            $result[] = [
                'message' => $matches['message'],
                'channel' => $matches['channel'],
                'date' => $matches['date'],
                'level' => $matches['level'],
            ];
        }

        return new JsonResponse($result, Response::HTTP_OK, ['file-size' => $file->key()]);
    }

    private function getFilePathByBag(Request $request): string
    {
        if (!$request->query->has('file')) {
            throw RoutingException::missingRequestParameter('file');
        }

        $fileName = $request->query->get('file');

        // prevent path travel
        $files = array_column($this->getFiles(), 'name');
        if (!\in_array($fileName, $files, true)) {
            throw RoutingException::missingRequestParameter('file');
        }

        return $this->logDir . $fileName;
    }

    /**
     * @return array{name: string}[]
     */
    private function getFiles(): array
    {
        $finder = new Finder();
        $finder
            ->in($this->logDir)
            ->files()
            ->ignoreDotFiles(true)
            ->sortByChangedTime()
            ->reverseSorting()
        ;

        $files = [];

        foreach ($finder->getIterator() as $file) {
            $files[] = [
                'name' => $file->getFilename(),
            ];
        }

        return $files;
    }
}
