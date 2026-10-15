<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\DataIntegrityChecker;

use Frosh\Tools\Components\Health\Checker\DataIntegrityChecker\FlowActionDeletedReferenceChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\Framework\Uuid\Uuid;

#[CoversClass(FlowActionDeletedReferenceChecker::class)]
class FlowActionDeletedReferenceCheckerTest extends DataIntegrityCheckerTestCase
{
    private const ID = 'flow-action-deleted-references';

    private FlowActionDeletedReferenceChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->checker = static::getContainer()->get(FlowActionDeletedReferenceChecker::class);
        $this->connection->executeStatement('UPDATE flow SET active = 0');
    }

    public function testReportsOkForExistingReferencesAndInactiveFlows(): void
    {
        $tagId = $this->createTag();
        $activeFlowId = $this->createFlow(true);
        $this->createSequence($activeFlowId, 'action.mail.send', ['mailTemplateId' => $this->fetchHexId('mail_template'), 'recipient' => ['type' => 'default', 'data' => []]]);
        $this->createSequence($activeFlowId, 'action.add.order.tag', ['tagIds' => [$tagId => 'Frosh']]);
        $this->createSequence($activeFlowId, 'action.add.customer.tag', ['tagIds' => [$tagId => 'Frosh']]);
        $this->createSequence($activeFlowId, 'action.change.customer.group', ['customerGroupId' => $this->fetchHexId('customer_group')]);
        $this->createSequence($activeFlowId, 'action.stop.flow', []);
        $this->createSequence($activeFlowId, null, null);

        $inactiveFlowId = $this->createFlow(false);
        $this->createSequence($inactiveFlowId, 'action.mail.send', ['mailTemplateId' => Uuid::randomHex()]);
        $this->createSequence($inactiveFlowId, 'action.change.customer.group', ['customerGroupId' => Uuid::randomHex()]);

        $invalidFlowId = $this->createFlow(true, true);
        $this->createSequence($invalidFlowId, 'action.mail.send', ['mailTemplateId' => Uuid::randomHex()]);
        $this->createSequence($invalidFlowId, 'action.add.order.tag', ['tagIds' => [Uuid::randomHex() => 'Deleted']]);
        $this->createSequence($invalidFlowId, 'action.change.customer.group', ['customerGroupId' => Uuid::randomHex()]);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 0);
    }

    public function testCountsActionsOfActiveFlowsReferencingDeletedEntities(): void
    {
        $tagId = $this->createTag();
        $flowId = $this->createFlow(true);
        $this->createSequence($flowId, 'action.mail.send', ['mailTemplateId' => Uuid::randomHex(), 'recipient' => ['type' => 'default', 'data' => []]]);
        $this->createSequence($flowId, 'action.add.order.tag', ['tagIds' => [$tagId => 'Frosh', Uuid::randomHex() => 'Deleted']]);
        $this->createSequence($flowId, 'action.add.customer.tag', ['tagIds' => [Uuid::randomHex() => 'Deleted']]);
        $this->createSequence($flowId, 'action.change.customer.group', ['customerGroupId' => Uuid::randomHex()]);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 4);
    }

    private function fetchHexId(string $table): string
    {
        return (string) $this->connection->fetchOne(\sprintf('SELECT LOWER(HEX(id)) FROM `%s` LIMIT 1', $table));
    }

    private function createTag(): string
    {
        $id = Uuid::randomHex();

        $this->connection->insert('tag', [
            'id' => Uuid::fromHexToBytes($id),
            'name' => 'Frosh data integrity check ' . $id,
            'created_at' => self::now(),
        ]);

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

    /**
     * @param array<string, mixed>|null $config
     */
    private function createSequence(string $flowId, ?string $actionName, ?array $config): void
    {
        $this->connection->insert('flow_sequence', [
            'id' => Uuid::randomBytes(),
            'flow_id' => $flowId,
            'action_name' => $actionName,
            'config' => $config === null ? null : json_encode($config, \JSON_THROW_ON_ERROR | \JSON_FORCE_OBJECT),
            'position' => 1,
            'display_group' => 1,
            'true_case' => 0,
            'created_at' => self::now(),
        ]);
    }
}
