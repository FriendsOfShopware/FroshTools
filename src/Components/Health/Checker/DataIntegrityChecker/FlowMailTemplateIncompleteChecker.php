<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\DataIntegrityChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Shopware\Core\Content\MailTemplate\MailTemplateDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

class FlowMailTemplateIncompleteChecker implements DataIntegrityCheckerInterface, CheckerInterface, AffectedEntitiesProviderInterface
{
    private const ID = 'flow-mail-template-incomplete';

    private const AFFECTED_QUERY = <<<'SQL'
        SELECT mt.id
        FROM flow_sequence fs
        INNER JOIN flow f ON f.id = fs.flow_id
        INNER JOIN mail_template mt ON mt.id = UNHEX(JSON_UNQUOTE(JSON_EXTRACT(fs.config, '$.mailTemplateId')))
        LEFT JOIN mail_template_translation mtt
            ON mtt.mail_template_id = mt.id
            AND mtt.language_id = :systemLanguageId
        WHERE f.active = 1
          AND f.invalid = 0
          AND fs.action_name = :sendMailAction
          AND JSON_TYPE(JSON_EXTRACT(fs.config, '$.mailTemplateId')) = 'STRING'
          AND (CHAR_LENGTH(COALESCE(mtt.subject, '')) = 0
              OR CHAR_LENGTH(COALESCE(mtt.content_html, '')) = 0
              OR CHAR_LENGTH(COALESCE(mtt.content_plain, '')) = 0)
        SQL;

    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function getId(): string
    {
        return self::ID;
    }

    public function collect(HealthCollection $collection): void
    {
        $count = (int) $this->connection->fetchOne(
            \sprintf('SELECT COUNT(DISTINCT affected.id) FROM (%s) affected', self::AFFECTED_QUERY),
            $this->queryParameters(),
        );

        $collection->add(DataIntegrityCheckResult::fromCount(
            self::ID,
            'Mail templates of active flows without a complete default translation',
            $count,
        ));
    }

    public function getAffectedEntities(int $limit, int $offset): AffectedEntities
    {
        return AffectedEntities::fromQuery($this->connection, MailTemplateDefinition::ENTITY_NAME, self::AFFECTED_QUERY, $this->queryParameters(), [], $limit, $offset);
    }

    /**
     * @return array{systemLanguageId: string, sendMailAction: string}
     */
    private function queryParameters(): array
    {
        return [
            'systemLanguageId' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'sendMailAction' => 'action.mail.send',
        ];
    }
}
