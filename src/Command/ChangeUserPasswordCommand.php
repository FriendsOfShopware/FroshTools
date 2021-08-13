<?php declare(strict_types=1);

namespace Frosh\Tools\Command;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ChangeUserPasswordCommand extends Command
{
    public static $defaultName = 'frosh:user:change:password';
    public static $defaultDescription = 'Change user password';

    private EntityRepositoryInterface $userRepository;

    public function __construct(EntityRepositoryInterface $userRepository)
    {
        parent::__construct();
        $this->userRepository = $userRepository;
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

        if ($password === null && $input->isInteractive()) {
            $password = $io->askHidden('Password');
        }

        if ($password === null) {
            throw new \InvalidArgumentException('Password is required');
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('username', $input->getArgument('username')));

        $id = $this->userRepository->searchIds($criteria, $context)->firstId();

        if ($id === null) {
            throw new \InvalidArgumentException(sprintf('Cannot find any user with username: %s', $input->getArgument('username')));
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
