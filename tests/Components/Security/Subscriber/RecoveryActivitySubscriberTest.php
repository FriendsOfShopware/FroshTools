<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Security\Subscriber;

use Frosh\Tools\Components\Security\Subscriber\RecoveryActivitySubscriber;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\Event\CustomerAccountRecoverRequestEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\User\Aggregate\UserRecovery\UserRecoveryEntity;
use Shopware\Core\System\User\Recovery\UserRecoveryRequestEvent;
use Symfony\Component\EventDispatcher\EventDispatcher;

/** @internal */
class RecoveryActivitySubscriberTest extends TestCase
{
    public function testUserRecoveryRecordsTargetWithoutResetUrl(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info')->with('user:recovery_requested', ['actorType' => 'anonymous', 'entityId' => 'user']);
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new RecoveryActivitySubscriber($logger));
        $recovery = new UserRecoveryEntity();
        $recovery->setUserId('user');
        $dispatcher->dispatch(new UserRecoveryRequestEvent($recovery, 'https://example.com/secret-reset-token', Context::createDefaultContext()), UserRecoveryRequestEvent::EVENT_NAME);
    }

    public function testCustomerRecoveryUsesItsOwnTarget(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info')->with('customer:recovery_requested', ['actorType' => 'anonymous', 'entityId' => 'customer']);
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new RecoveryActivitySubscriber($logger));
        $event = static::createStub(CustomerAccountRecoverRequestEvent::class);
        $event->method('getCustomerId')->willReturn('customer');
        $dispatcher->dispatch($event, CustomerAccountRecoverRequestEvent::EVENT_NAME);
    }
}
