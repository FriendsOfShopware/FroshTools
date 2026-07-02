<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\PerformanceChecker;

use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class DisabledMailUpdatesChecker implements PerformanceCheckerInterface, CheckerInterface
{
    public function __construct(private readonly ParameterBagInterface $params)
    {
    }

    public function collect(HealthCollection $collection): void
    {
        if (!$this->params->has('shopware.mail.update_mail_variables_on_send')) {
            return;
        }

        $setting = $this->params->get('shopware.mail.update_mail_variables_on_send');

        if (!$setting) {
            return;
        }

        $result = SettingsResult::warning('mail_variables', 'MailVariables updates', 'enabled', 'disabled');

        $collection->add($result);
    }
}
