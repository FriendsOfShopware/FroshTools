<?php

declare(strict_types=1);

namespace Frosh\Tools\Command;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('frosh:shopmon:create-acl-role', 'Create Shopmon ACL role')]
class ShopmonCreateAclRole extends Command
{
    private const SHOPMON_ACL_ROLE_NAME = 'shopmon_acl_role';
    private const SHOPMON_ACL_ROLE_DESCRIPTION = 'This role has the necessary permissions to use Shopmon';
    private const SHOPMON_ACL_ROLE_ID = '018dd6ae4c4072b1b5887fe8d3b9b95a';

    /**
     * @param EntityRepository<AclRoleCollection> $aclRoleRepository
     * @param EntityRepository<EntityCollection> $userRepository
     * @param Connection $connection
     */
    public function __construct(
        private readonly EntityRepository $aclRoleRepository,
        private readonly EntityRepository $userRepository,
        private readonly Connection       $connection
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('username', null, InputOption::VALUE_OPTIONAL, 'User that will be assigned the role');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($this->roleExists()) {
            $io->warning(sprintf('ACL role with the name `%s` already exists', self::SHOPMON_ACL_ROLE_NAME));
            return Command::SUCCESS;
        }

        $userId = null;
        if ($input->getOption('username') !== null) {
            $userId = $this->userExists($input->getOption('username'));
            if (!$userId) {
                $io->error(sprintf('User with the username `%s` does not exist', $input->getOption('username')));
                return Command::FAILURE;
            }
        }

        try {
            $this->createRole();
        } catch (WriteException $exception) {
            $io->error('Something went wrong.');
            $messages = $this->createWriteExceptionMessages($exception);
            $io->listing($messages);
            return Command::FAILURE;
        }
        $io->success(sprintf('ACL role with the name `%s` has been created', self::SHOPMON_ACL_ROLE_NAME));

        if ($userId === null) {
            return Command::SUCCESS;
        }

        try {
            $this->assignRoleToUser($userId);
        } catch (\Exception $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }
        $io->success(sprintf('ACL role been assigned to the user with the username `%s`', $input->getOption('username')));

        return Command::SUCCESS;
    }

    private function roleExists(): bool
    {
        $context = Context::createDefaultContext();
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', self::SHOPMON_ACL_ROLE_NAME));
        $criteria->setLimit(1);

        return $this->aclRoleRepository->search($criteria, $context)->getTotal() > 0;
    }

    private function userExists(string $username): ?string
    {
        $context = Context::createDefaultContext();
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('username', $username));
        $criteria->setLimit(1);

        return $this->userRepository->searchIds($criteria, $context)->firstId();
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
            'id' => self::SHOPMON_ACL_ROLE_ID,
            'name' => self::SHOPMON_ACL_ROLE_NAME,
            'description' => self::SHOPMON_ACL_ROLE_DESCRIPTION,
            'privileges' => $aclPermissions,
        ];

        $context = Context::createDefaultContext();
        $this->aclRoleRepository->create([$aclRole], $context);
    }

    private function assignRoleToUser(string $userId): void
    {
        $sql = <<<'SQL'
            INSERT INTO `acl_user_role` (`user_id`, `acl_role_id`, `created_at`)
            VALUES (:user_id, :acl_role_id, :created_at)
        SQL;

        $data = [
            'user_id' => Uuid::fromHexToBytes($userId),
            'acl_role_id' => Uuid::fromHexToBytes(self::SHOPMON_ACL_ROLE_ID),
            'created_at' => (new \DateTime())->format('Y-m-d H:i:s')
        ];

        $query = new RetryableQuery($this->connection, $this->connection->prepare($sql));
        $query->execute($data);
    }

    private function createWriteExceptionMessages(WriteException $exception): array
    {
        $messages = [];
        foreach ($exception->getExceptions() as $err) {
            if ($err instanceof WriteConstraintViolationException) {
                foreach ($err->getViolations() as $violation) {
                    $messages[] = $violation->getPropertyPath() . ': ' . $violation->getMessage();
                }
            }
        }

        return $messages;
    }
}
