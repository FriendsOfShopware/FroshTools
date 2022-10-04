<?php declare(strict_types=1);

namespace Frosh\Tools\Command;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Mail\Service\AbstractMailService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskDefinition;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\ParameterBag;

class MonitorCommand extends Command
{
    private const MONITOR_EMAIL_OPTION = 'email';
    private const MONITOR_SALESCHANNEL_ARG = 'sales-channel';
    public static $defaultName = 'frosh:monitor';
    public static $defaultDescription = 'Monitor your scheduled task and queue with this command and get notified via email.';

    private AbstractMailService       $mailService;
    private SystemConfigService       $configService;
    private Connection                $connection;
    private EntityRepositoryInterface $scheduledTaskRepository;

    public function __construct(AbstractMailService $mailService, SystemConfigService $configService, Connection $connection, EntityRepositoryInterface $scheduledTaskRepository)
    {
        parent::__construct();
        $this->mailService = $mailService;
        $this->configService = $configService;
        $this->connection = $connection;
        $this->scheduledTaskRepository = $scheduledTaskRepository;
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
                'FroshTools.config.monitorMail');
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
                <div style="font-family:arial; font-size:12px;">
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
        $oldestMessage = (int) $this->connection->fetchOne('SELECT IFNULL(MIN(published_at), 0) FROM enqueue');
        $oldestMessage /= 10000;
        $minutes = $this->configService->getInt(
            'FroshTools.config.monitorQueueGraceTime');

        if ($oldestMessage && ($oldestMessage + ($minutes * 60)) < time()) {
            return true;
        }

        return false;
    }

    private function scheduledTaskFailed(): bool
    {
        $minutes = $this->configService->getInt(
            'FroshTools.config.monitorTaskGraceTime');

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

        if (count($oldTasks) === 0) {
            return false;
        }

        return true;
    }
}
