<?php

declare(strict_types=1);

namespace Frosh\Tools\Controller;

use Frosh\Tools\Components\Log\MonologLogReaderInterface;
use Shopware\Core\Framework\Routing\RoutingException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/api/_action/frosh-tools', defaults: ['_routeScope' => ['api'], '_acl' => ['frosh_tools:read']])]
class LogController extends AbstractController
{
    private readonly string $logDir;

    public function __construct(
        #[Autowire(param: 'kernel.logs_dir')]
        string $logDir,
        private readonly MonologLogReaderInterface $monologLogReader,
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

        // Native path: open file in C, reverse-scan + SIMD parse, return page.
        // Fallback path: PHP LineReader + preg_match (same response shape).
        $page = $this->monologLogReader->readBackwards($filePath, $offset, $limit);

        return new JsonResponse($page['entries'], Response::HTTP_OK, [
            'file-size' => $page['total'],
            'x-monolog-parser' => $this->monologLogReader->backend(),
        ]);
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
        if (!is_dir($this->logDir)) {
            return [];
        }

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
                'name' => $file->getRelativePathname(),
            ];
        }

        return $files;
    }
}
