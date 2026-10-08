<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\DataIntegrityChecker;

use Frosh\Tools\Components\Health\Checker\DataIntegrityChecker\FlowMailTemplateIncompleteChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

#[CoversClass(FlowMailTemplateIncompleteChecker::class)]
class FlowMailTemplateIncompleteCheckerTest extends DataIntegrityCheckerTestCase
{
    private const ID = 'flow-mail-template-incomplete';

    private FlowMailTemplateIncompleteChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->checker = static::getContainer()->get(FlowMailTemplateIncompleteChecker::class);
        $this->connection->executeStatement('UPDATE flow SET active = 0');
    }

    public function testReportsOkForCompleteTemplatesAndTemplatesOfInactiveFlows(): void
    {
        $activeFlowId = $this->createFlow(true);
        $this->createMailSequence($activeFlowId, $this->createMailTemplate(['subject' => 'Subject', 'content_html' => '<p>Html</p>', 'content_plain' => 'Plain']));
        $this->createMailSequence($activeFlowId, Uuid::randomHex());

        $inactiveFlowId = $this->createFlow(false);
        $this->createMailSequence($inactiveFlowId, $this->createMailTemplate(null));
        $this->createMailSequence($this->createFlow(true, true), $this->createMailTemplate(null));

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 0);
    }

    public function testCountsTemplatesOfActiveFlowsWithoutCompleteSystemLanguageTranslation(): void
    {
        $flowId = $this->createFlow(true);
        $otherFlowId = $this->createFlow(true);

        $withoutTranslation = $this->createMailTemplate(null);
        $this->createMailSequence($flowId, $withoutTranslation);
        $this->createMailSequence($otherFlowId, $withoutTranslation);

        $this->createMailSequence($flowId, $this->createMailTemplate(['subject' => 'Subject', 'content_html' => '<p>Html</p>', 'content_plain' => '']));
        $this->createMailSequence($flowId, $this->createMailTemplate(['subject' => null, 'content_html' => '<p>Html</p>', 'content_plain' => 'Plain']));

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 3);
    }

    /**
     * @param array{subject: ?string, content_html: ?string, content_plain: ?string}|null $translation
     */
    private function createMailTemplate(?array $translation): string
    {
        $id = Uuid::randomHex();

        $this->connection->insert('mail_template', [
            'id' => Uuid::fromHexToBytes($id),
            'created_at' => self::now(),
        ]);

        if ($translation !== null) {
            $this->connection->insert('mail_template_translation', [
                'mail_template_id' => Uuid::fromHexToBytes($id),
                'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                'sender_name' => 'Frosh',
                'created_at' => self::now(),
            ] + $translation);
        }

        return $id;
    }

    private function createFlow(bool $active, bool $invalid = false): string
    {
        $id = Uuid::randomBytes();

        $this->connection->insert('flow', [
            'id' => $id,
            'name' => 'Frosh data integrity check flow',
            'event_name' => 'checkout.order.placed',
            'active' => (int) $active,
            'invalid' => (int) $invalid,
            'created_at' => self::now(),
        ]);

        return $id;
    }

    private function createMailSequence(string $flowId, string $mailTemplateId): void
    {
        $this->connection->insert('flow_sequence', [
            'id' => Uuid::randomBytes(),
            'flow_id' => $flowId,
            'action_name' => 'action.mail.send',
            'config' => json_encode(['mailTemplateId' => $mailTemplateId, 'recipient' => ['type' => 'default', 'data' => new \stdClass()]], \JSON_THROW_ON_ERROR),
            'position' => 1,
            'display_group' => 1,
            'true_case' => 0,
            'created_at' => self::now(),
        ]);
    }
}
