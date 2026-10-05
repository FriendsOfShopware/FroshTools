<?php

declare(strict_types=1);

namespace Frosh\Tools\Controller;

use Frosh\Tools\Acl\FroshToolsPrivileges;
use Frosh\Tools\Components\Security\Activity\ActivityStore;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedJsonResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Route(path: '/api/_action/frosh-tools/security', defaults: ['_routeScope' => ['api'], '_acl' => [FroshToolsPrivileges::SECURITY_READ]])]
class SecurityActivityController extends AbstractController
{
    public function __construct(private readonly ActivityStore $store)
    {
    }

    #[Route(path: '/activity', name: 'api.frosh.tools.security.activity', methods: ['GET'])]
    public function activity(Request $request): JsonResponse
    {
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 25);
        if ($page < 1 || $page > 10000 || $limit < 1 || $limit > 100) {
            throw new BadRequestHttpException('Page must be between 1 and 10000; limit must be between 1 and 100.');
        }

        return new JsonResponse(
            $this->store->search($page, $limit, ...$this->filters($request)),
            headers: ['Cache-Control' => 'no-store'],
        );
    }

    #[Route(path: '/activity/export', name: 'api.frosh.tools.security.activity.export', methods: ['GET'])]
    public function export(Request $request): StreamedJsonResponse
    {
        $filters = $this->filters($request);

        return new StreamedJsonResponse([
            'exportedAt' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format(\DATE_ATOM),
            'entries' => $this->store->export(...$filters),
        ], headers: [
            'Cache-Control' => 'no-store',
            'Content-Disposition' => 'attachment; filename="security-activity.json"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    #[Route(path: '/activity/options', name: 'api.frosh.tools.security.activity.options', methods: ['GET'])]
    public function options(Request $request): JsonResponse
    {
        $field = $request->query->getString('field');
        if (!\in_array($field, ['actor', 'action'], true)) {
            throw new BadRequestHttpException('Field must be actor or action.');
        }

        $page = $request->query->getInt('page', 1);
        if ($page < 1 || $page > 10000) {
            throw new BadRequestHttpException('Page must be between 1 and 10000.');
        }

        return new JsonResponse($this->store->filterOptions($field, $this->filterText($request, 'term'), $page), headers: ['Cache-Control' => 'no-store']);
    }

    /**
     * @return array{string, string, ?\DateTimeImmutable, ?\DateTimeImmutable, bool, string, string, string, string}
     */
    private function filters(Request $request): array
    {
        $clientIp = trim($request->query->getString('clientIp'));
        if ($clientIp !== '' && filter_var($clientIp, FILTER_VALIDATE_IP) === false) {
            throw new BadRequestHttpException('Client IP must be a valid IPv4 or IPv6 address.');
        }
        $userId = $request->query->getString('userId');
        if ($userId !== '' && !Uuid::isValid($userId)) {
            throw new BadRequestHttpException('User ID must be a valid UUID.');
        }
        $subjectType = $request->query->getString('subjectType');
        $subjectId = strtolower($request->query->getString('subjectId'));
        if (($subjectType !== '' || $subjectId !== '') && (!\in_array($subjectType, ['users', 'integrations', 'keys'], true) || !Uuid::isValid($subjectId))) {
            throw new BadRequestHttpException('Subject requires a supported type and valid UUID.');
        }
        $action = $this->filterText($request, 'action');
        $actor = $this->filterText($request, 'actor');
        $from = $this->date($request, 'from');
        $to = $this->date($request, 'to');
        if ($from !== null && $to !== null && $from > $to) {
            throw new BadRequestHttpException('The start date must not be after the end date.');
        }

        return [$action, $actor, $from, $to, $request->query->getBoolean('exact'), $clientIp, $userId, $subjectType, $subjectId];
    }

    private function filterText(Request $request, string $key): string
    {
        $value = $request->query->getString($key);
        if ($key !== 'actor' || !$request->query->getBoolean('exact')) {
            $value = trim($value);
        }
        $maxLength = $key === 'actor' ? 255 : 100;
        if (mb_strlen($value) > $maxLength) {
            throw new BadRequestHttpException('Filter exceeds its maximum length.');
        }

        return $value;
    }

    private function date(Request $request, string $key): ?\DateTimeImmutable
    {
        $value = $request->query->getString($key);
        if ($value === '') {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value) !== 1) {
            throw new BadRequestHttpException('Dates must use YYYY-MM-DD.');
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, new \DateTimeZone('UTC'));
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new BadRequestHttpException('Dates must use YYYY-MM-DD.');
        }

        return $date;
    }
}
