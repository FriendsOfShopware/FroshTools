<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\DataIntegrityChecker;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;

class FlowActionDeletedReferenceChecker implements DataIntegrityCheckerInterface, CheckerInterface
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function collect(HealthCollection $collection): void
    {
        $count = (int) $this->connection->fetchOne(
            <<<'SQL'
                SELECT COUNT(DISTINCT broken.sequence_id)
                FROM (
                    SELECT fs.id AS sequence_id
                    FROM flow_sequence fs
                    INNER JOIN flow f ON f.id = fs.flow_id
                    LEFT JOIN mail_template mt ON mt.id = UNHEX(JSON_UNQUOTE(JSON_EXTRACT(fs.config, '$.mailTemplateId')))
                    WHERE f.active = 1
                      AND f.invalid = 0
                      AND fs.action_name = :sendMailAction
                      AND JSON_TYPE(JSON_EXTRACT(fs.config, '$.mailTemplateId')) = 'STRING'
                      AND mt.id IS NULL
                    UNION ALL
                    SELECT fs.id AS sequence_id
                    FROM flow_sequence fs
                    INNER JOIN flow f ON f.id = fs.flow_id
                    LEFT JOIN customer_group cg ON cg.id = UNHEX(JSON_UNQUOTE(JSON_EXTRACT(fs.config, '$.customerGroupId')))
                    WHERE f.active = 1
                      AND f.invalid = 0
                      AND fs.action_name = :changeCustomerGroupAction
                      AND JSON_TYPE(JSON_EXTRACT(fs.config, '$.customerGroupId')) = 'STRING'
                      AND JSON_UNQUOTE(JSON_EXTRACT(fs.config, '$.customerGroupId')) <> ''
                      AND cg.id IS NULL
                    UNION ALL
                    SELECT fs.id AS sequence_id
                    FROM flow_sequence fs
                    INNER JOIN flow f ON f.id = fs.flow_id
                    CROSS JOIN JSON_TABLE(JSON_KEYS(fs.config, '$.tagIds'), '$[*]' COLUMNS (tag_id VARCHAR(255) PATH '$')) tag_reference
                    LEFT JOIN tag t ON t.id = UNHEX(tag_reference.tag_id)
                    WHERE f.active = 1
                      AND f.invalid = 0
                      AND fs.action_name IN (:addTagActions)
                      AND t.id IS NULL
                ) broken
                SQL,
            [
                'sendMailAction' => 'action.mail.send',
                'changeCustomerGroupAction' => 'action.change.customer.group',
                'addTagActions' => ['action.add.order.tag', 'action.add.customer.tag'],
            ],
            ['addTagActions' => ArrayParameterType::STRING],
        );

        $collection->add(DataIntegrityCheckResult::fromCount(
            'flow-action-deleted-references',
            'Active flow actions referencing deleted entities',
            $count,
        ));
    }
}
