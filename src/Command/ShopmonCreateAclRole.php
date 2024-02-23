<?php

declare(strict_types=1);

namespace Frosh\Tools\Command;

use Shopware\Core\Framework\Api\Acl\Role\AclRoleCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('frosh:shopmon:create-acl-role', 'Create Shopmon ACL role')]
class ShopmonCreateAclRole extends Command
{
    /**
     * @param EntityRepository<AclRoleCollection> $aclRoleRepository
     */
    public function __construct(
        private readonly EntityRepository $aclRoleRepository
    ) {
        parent::__construct();
    }

    private const SHOPMON_ACL_ROLE_NAME = 'shopmon_acl_role';
    private const SHOPMON_ACL_ROLE_DESCRIPTION = 'This role has the necessary permissions to use Shopmon';

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($this->roleExists()) {
            $io->warning('ACL role with the name `' . self::SHOPMON_ACL_ROLE_NAME . '` already exists');
            return Command::SUCCESS;
        }

        try {
            $this->createRole();
            $io->success('Role with the name `' . self::SHOPMON_ACL_ROLE_NAME . '` has been created');
        } catch (WriteException $exception) {
            $io->error('Something went wrong.');

            $messages = [];
            foreach ($exception->getExceptions() as $err) {
                if ($err instanceof WriteConstraintViolationException) {
                    foreach ($err->getViolations() as $violation) {
                        $messages[] = $violation->getPropertyPath() . ': ' . $violation->getMessage();
                    }
                }
            }

            $io->listing($messages);
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function roleExists(): bool
    {
        $context = Context::createDefaultContext();
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', self::SHOPMON_ACL_ROLE_NAME));
        $criteria->setLimit(1);

        return $this->aclRoleRepository->searchIds($criteria, $context)->getTotal() > 0;
    }

    private function createRole(): void
    {
        $aclPermissions = [
            'app:read',
            'product:read',
            'product:write',
            'system_config:read',
            'scheduled_task:read',
            'frosh_tools:read',
            'system:clear:cache',
            'system:cache:info'
        ];

        $aclRole = [
            'name' => self::SHOPMON_ACL_ROLE_NAME,
            'description' => self::SHOPMON_ACL_ROLE_DESCRIPTION,
            'privileges' => $aclPermissions,
        ];

        $context = Context::createDefaultContext();
        $this->aclRoleRepository->create([$aclRole], $context);
    }
}
