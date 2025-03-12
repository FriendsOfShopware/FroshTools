<?php

declare(strict_types=1);

namespace Frosh\Tools\Controller;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/api/_action/frosh-tools', defaults: ['_routeScope' => ['api'], '_acl' => ['frosh_tools:read']])]
class GmvController extends AbstractController
{
    /**
     * @param ServiceLocator<ReceiverInterface> $transportLocator
     */
    public function __construct(
        private readonly Connection $connection,
    ) {}

    #[Route(path: '/gmv/list', name: 'api.frosh.tools.gmv.list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $start = date('Y-01-01', strtotime('-1 year'));
        $end = date('Y-m-d');

        // get the liveVersionUUID
        $context = Context::createDefaultContext();
        $liveVersionUUID = $context->getVersionId();

        $sql = "
            SELECT ROUND(SUM(`order`.`amount_total`), 2) AS `turnover_total`,
                   ROUND(SUM(`order`.`amount_net`), 2) AS `turnover_net`,
                   COUNT(`order`.`id`) AS `order_count`,
                   DATE_FORMAT(`order`.`order_date`, '%Y-%m') AS `date`,
                   `currency`.`iso_code` AS `currency_iso_code`,
                   `currency`.`factor` AS `currency_factor`
            FROM `order`
            INNER JOIN `currency` on `order`.currency_id = `currency`.`id`
            WHERE `order`.`order_date` BETWEEN :start AND :end
              AND `order`.`version_id` = :liveVersionId
              AND (JSON_CONTAINS(`order`.`custom_fields`, 'true', '$.saas_test_order') IS NULL OR JSON_CONTAINS(`order`.`custom_fields`, 'true', '$.saas_test_order') = 0)
            GROUP BY DATE_FORMAT(`order`.`order_date`, '%Y-%m'), `order`.`currency_id`
        ";

        $list = $this->connection->executeQuery($sql, [
            'start' => $start,
            'end' => $end,
            'liveVersionId' => Uuid::fromHexToBytes($liveVersionUUID),
        ])->fetchAllAssociative();

        // Group data by year
        $gmvYearly = [];
        foreach ($list as $entry) {
            $year = substr($entry['date'], 0, 4);
            $currencyIsoCode = $entry['currency_iso_code'];
            $key = $year.$currencyIsoCode;

            if (!isset($gmvYearly[$key])) {
                $gmvYearly[$key] = [
                    'date' => $year,
                    'turnover_total' => 0,
                    'turnover_net' => 0,
                    'order_count' => 0,
                    'currency_iso_code' => $entry['currency_iso_code'],
                    'currency_factor' => $entry['currency_factor'],
                ];
            }

            $gmvYearly[$key]['turnover_total'] += $entry['turnover_total'];
            $gmvYearly[$key]['turnover_net'] += $entry['turnover_net'];
            $gmvYearly[$key]['order_count'] += $entry['order_count'];
        }

        $gmvData = [
            'year' => $gmvYearly,
            'month' => $list
        ];

        return new JsonResponse($gmvData);
    }
}
