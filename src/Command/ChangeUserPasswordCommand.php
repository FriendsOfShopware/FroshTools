<?php

declare(strict_types=1);

namespace Frosh\Tools\Command;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\User\UserCollection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('frosh:user:change:password', 'Change user password')]
class ChangeUserPasswordCommand extends Command
{
    /**
     * @param EntityRepository<UserCollection> $userRepository
     */
    public function __construct(private readonly EntityRepository $userRepository)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('username', InputArgument::REQUIRED, 'The username');
        $this->addArgument('password', InputArgument::OPTIONAL, 'The user password');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $context = Context::createDefaultContext();

        $io = new SymfonyStyle($input, $output);
        $password = $input->getArgument('password');
        $username = $input->getArgument('username');

        if ($password === null && $input->isInteractive()) {
            $password = $io->askHidden('Password');
        }

        if ($password === null) {
            throw new \InvalidArgumentException('Password is required');
        }

        if (!\is_string($username)) {
            throw new \InvalidArgumentException('Username must be a string');
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('username', $username));

        $id = $this->userRepository->searchIds($criteria, $context)->firstId();

        if ($id === null) {
            throw new \InvalidArgumentException(sprintf('Cannot find any user with username: %s', $username));
        }

        $this->userRepository->update([
            [
                'id' => $id,
                'password' => $password,
            ],
        ], $context);

        $io->success('Successfully changed the password');

        return self::SUCCESS;
    }
}
