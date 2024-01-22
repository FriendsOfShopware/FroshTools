<?php

declare(strict_types=1);

namespace Frosh\Tools\Command;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Mail\Service\AbstractMailService;
use Shopware\Core\Content\Mail\Service\MailService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskDefinition;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\ParameterBag;

#[AsCommand('frosh:monitor', 'Monitor your scheduled tasks and queue with this command and get notified via email.')]
class MonitorCommand extends Command
{
    private const MONITOR_EMAIL_OPTION = 'email';
    private const MONITOR_SALESCHANNEL_ARG = 'sales-channel';

    /**
     * @param EntityRepository<ScheduledTaskCollection> $scheduledTaskRepository
     */
    public function __construct(
        #[Autowire(service: MailService::class)]
        private readonly AbstractMailService $mailService,
        private readonly SystemConfigService $configService,
        private readonly Connection $connection,
        private readonly EntityRepository $scheduledTaskRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('sales-channel', InputArgument::REQUIRED, 'Sales Channel ID.');
        $this->addOption(self::MONITOR_EMAIL_OPTION, 'em', InputOption::VALUE_OPTIONAL, 'Custom mail address');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $context = Context::createDefaultContext();

        if ($input->getOption(self::MONITOR_EMAIL_OPTION)) {
            $recepientMail = $input->getOption(self::MONITOR_EMAIL_OPTION);
            $errorSource = 'CLI option';
        } else {
            $recepientMail = $this->configService->getString(
                'FroshTools.config.monitorMail'
            );
            $errorSource = 'plugin config';
        }

        if (!filter_var($recepientMail, \FILTER_VALIDATE_EMAIL)) {
            $output->writeln('<error>Invalid email format in ' . $errorSource . '</error>');

            return self::INVALID;
        }

        if (!empty($recepientMail) && ($this->queueFailed() || $this->scheduledTaskFailed())) {
            $data = new ParameterBag();
            $data->set(
                'recipients',
                [
                    $recepientMail => 'Admin',
                ]
            );
            $data->set('senderName', 'Froshtools | Admin');

            $htmlMailContent = <<<'MAIL'
                <div>
                    <p>
                        Dear Admin,<br/>
                        <br/>
                        your message queue or scheduled tasks are not working as expected.<br/>
                        <br/>
                        <br/>
                        Check your queues and tasks <a href="{{ salesChannel.domains|first.url }}/admin#/frosh/tools/index/index">here</a>
                    </p>
                </div>
                MAIL;
            $plainMailContent = 'Dear Admin,your message queue or scheduled tasks are not working as expected.Check your queues and tasks {{ salesChannel.domains|first.url }}/admin#/frosh/tools/index/index';

            $data->set('contentHtml', $htmlMailContent);
            $data->set('contentPlain', $plainMailContent);
            $data->set('salesChannelId', $input->getArgument(self::MONITOR_SALESCHANNEL_ARG));
            $data->set('subject', 'Froshtools message queue and scheduled task | Warning');

            $this->mailService->send($data->all(), $context);
        }

        return self::SUCCESS;
    }

    private function queueFailed(): bool
    {
        /** @var string $availableAt */
        $availableAt = $this->connection->fetchOne('SELECT IFNULL(MIN(available_at), 0) FROM messenger_messages');
        $oldestMessage = (int) strtotime($availableAt);
        $minutes = $this->configService->getInt(
            'FroshTools.config.monitorQueueGraceTime'
        );

        return $oldestMessage && ($oldestMessage + ($minutes * 60)) < time();
    }

    private function scheduledTaskFailed(): bool
    {
        $minutes = $this->configService->getInt(
            'FroshTools.config.monitorTaskGraceTime'
        );

        $date = new \DateTime();
        $date->modify(sprintf('-%d minutes', $minutes));

        $criteria = new Criteria();
        $criteria->addFilter(
            new RangeFilter(
                'nextExecutionTime',
                ['lte' => $date->format(\DATE_ATOM)]
            )
        );
        $criteria->addFilter(new NotFilter(
            NotFilter::CONNECTION_AND,
            [
                new EqualsFilter('status', ScheduledTaskDefinition::STATUS_INACTIVE),
            ]
        ));

        $oldTasks = $this->scheduledTaskRepository
            ->searchIds($criteria, Context::createDefaultContext())->getIds();

        return $oldTasks !== [];
    }
}
