<?php

declare(strict_types=1);

namespace Frosh\Tools\Command;

use Frosh\Tools\Components\Health\Checker\HealthChecker\QueueChecker;
use Frosh\Tools\Components\Health\Checker\HealthChecker\TaskChecker;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use Shopware\Core\Content\Mail\Service\AbstractMailService;
use Shopware\Core\Content\Mail\Service\MailService;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\ParameterBag;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

#[AsCommand('frosh:monitor', 'Monitor your scheduled tasks and message queue and get notified via email.')]
class MonitorCommand extends Command
{
    private const MONITOR_EMAIL_OPTION = 'email';
    private const MONITOR_SALESCHANNEL_ARG = 'sales-channel';
    private const MONITOR_CACHE_KEY = 'frosh_mail_send_once';

    private bool $wasSentBefore = false;

    public function __construct(
        #[Autowire(service: MailService::class)]
        private readonly AbstractMailService $mailService,
        private readonly SystemConfigService $configService,
        private readonly QueueChecker $queueChecker,
        private readonly TaskChecker $taskChecker,
        #[Autowire(service: 'cache.object')]
        private readonly CacheInterface $cache,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(self::MONITOR_SALESCHANNEL_ARG, InputArgument::REQUIRED, 'Sales Channel ID.');
        $this->addOption(self::MONITOR_EMAIL_OPTION, 'em', InputOption::VALUE_OPTIONAL, 'Custom mail address');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $context = Context::createDefaultContext();

        if ($input->getOption(self::MONITOR_EMAIL_OPTION)) {
            $recipientMail = $input->getOption(self::MONITOR_EMAIL_OPTION);
            $errorSource = 'CLI option';
        } else {
            $recipientMail = $this->configService->getString(
                'FroshTools.config.monitorMail',
            );
            $errorSource = 'plugin config';
        }

        if (empty($recipientMail) || !filter_var($recipientMail, \FILTER_VALIDATE_EMAIL)) {
            $output->writeln('<error>Empty or invalid email format in ' . $errorSource . '</error>');

            return self::INVALID;
        }

        if (!$this->checksFailed()) {
            //Remove cache to reset "timer" for next E-Mail
            $this->cache->delete(self::MONITOR_CACHE_KEY);
            return Command::SUCCESS;
        }

        if ($this->mailWasSentBefore()) {
            return Command::SUCCESS;
        }

        $this->sendMail($recipientMail, $input, $context);

        return self::SUCCESS;
    }

    private function checksFailed(): bool
    {
        $collection = new HealthCollection();
        $this->queueChecker->collect($collection);
        $this->taskChecker->collect($collection);

        /** @var SettingsResult $result */
        foreach ($collection as $result) {
            if ($result->state !== SettingsResult::GREEN && $result->state !== SettingsResult::INFO) {
                return true;
            }
        }
        return false;
    }

    private function mailWasSentBefore(): bool
    {
        $this->wasSentBefore = true;
        $sendOnce = $this->configService->getBool(
            'FroshTools.config.monitorTaskSingleMail',
        );

        if (!$sendOnce || $sendOnce == null) {
            return false;
        }

        $sendLifeTime = $this->configService->getInt(
            'FroshTools.config.monitorTaskSingleMailTime',
        );

        //convert minutes to seconds
        $sendLifeTime = $sendLifeTime * 60;

        //Send E-Mail on cache miss (No E-Mail was send beovr)
        $this->cache->get(self::MONITOR_CACHE_KEY, function (ItemInterface $item) use ($sendLifeTime): void {
            $item->expiresAfter($sendLifeTime);
            $this->wasSentBefore = false;
        });

        return $this->wasSentBefore;
    }

    private function sendMail(string $recipientMail, InputInterface $input, Context $context): void
    {
        $data = new ParameterBag();
        $data->set(
            'recipients',
            [
                $recipientMail => 'Admin',
            ],
        );
        $data->set('senderName', 'FroshTools | Admin');

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
        $plainMailContent = 'Dear Admin, your message queue or scheduled tasks are not working as expected. Check your queues and tasks {{ salesChannel.domains|first.url }}/admin#/frosh/tools/index/index';

        $data->set('contentHtml', $htmlMailContent);
        $data->set('contentPlain', $plainMailContent);
        $data->set('salesChannelId', $input->getArgument(self::MONITOR_SALESCHANNEL_ARG));
        $data->set('subject', 'FroshTools message queue and scheduled task | Warning');

        $this->mailService->send($data->all(), $context);
    }
}
