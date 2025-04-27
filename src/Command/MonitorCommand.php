<?php

declare(strict_types=1);

namespace Frosh\Tools\Command;

use Frosh\Tools\Components\Health\Checker\HealthChecker\QueueChecker;
use Frosh\Tools\Components\Health\Checker\HealthChecker\TaskChecker;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use Shopware\Core\Content\Mail\Service\AbstractMailSender;
use Shopware\Core\Content\Mail\Service\MailSender;
use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mime\Email;

#[AsCommand('frosh:monitor', 'Monitor your scheduled tasks and message queue and get notified via email.')]
class MonitorCommand extends Command
{
    private const MONITOR_EMAIL_OPTION = 'email';

    public function __construct(
        #[Autowire(service: MailSender::class)]
        private readonly AbstractMailSender $mailSender,
        private readonly SystemConfigService $configService,
        private readonly QueueChecker $queueChecker,
        private readonly TaskChecker $taskChecker,
        #[Autowire('%env(APP_URL)%')]
        private readonly string $appUrl,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('sales-channel', InputArgument::OPTIONAL, 'This argument has no effect. Only for backward compatibility');
        $this->addOption(self::MONITOR_EMAIL_OPTION, 'em', InputOption::VALUE_OPTIONAL, 'Custom mail address');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new ShopwareStyle($input, $output);

        if ($input->getArgument('sales-channel')) {
            $io->warning('The sales channel argument is deprecated and has no effect. It will be removed in a future release.');
        }

        $recipientMail = $input->getOption(self::MONITOR_EMAIL_OPTION) ?: $this->configService->getString(
            'FroshTools.config.monitorMail',
        );

        if (empty($recipientMail) || !filter_var($recipientMail, \FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Empty or invalid email format.');
        }

        if ($this->checksFailed()) {
            if (empty($this->appUrl)) {
                throw new \RuntimeException('APP URL is not configured');
            }

            $htmlMailContent = <<<'MAIL'
                <div>
                    <p>
                        Dear Admin,<br/>
                        <br/>
                        your message queue or scheduled tasks are not working as expected.<br/>
                        <br/>
                        <br/>
                        Check your queues and tasks <a href="{domain_url}/admin#/frosh/tools/index/index">here</a>
                    </p>
                </div>
                MAIL;
            $htmlMailContent = str_replace('{domain_url}', $this->appUrl, $htmlMailContent);

            $plainMailContent = 'Dear Admin, your message queue or scheduled tasks are not working as expected. Check your queues and tasks ' . $this->appUrl . '/admin#/frosh/tools/index/index';

            $email = new Email();
            $email->subject('FroshTools message queue and scheduled task | Warning');

            $email->from($this->getSender());
            $email->to($recipientMail);
            $email->html($htmlMailContent);
            $email->text($plainMailContent);

            $this->mailSender->send($email);
        }

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

    private function getSender(): string
    {
        return trim(
            $this->configService->getString(
                'core.basicInformation.email'
            )
        ) ?: trim(
            $this->configService->getString(
                'core.mailerSettings.senderAddress'
            )
        );
    }
}
