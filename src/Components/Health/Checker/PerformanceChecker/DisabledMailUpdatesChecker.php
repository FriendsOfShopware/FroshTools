<?php declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\PerformanceChecker;

use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class DisabledMailUpdatesChecker implements CheckerInterface
{
    private ParameterBagInterface $params;

    public function __construct(ParameterBagInterface $params)
    {
        $this->params = $params;
    }

    public function collect(HealthCollection $collection): void
    {
        if (!$this->params->has('shopware.mail.update_mail_variables_on_send')) {
            return;
        }

        $result = SettingsResult::ok('mail_variables', 'MailVariables are not updated frequently', 'disabled');
        $setting = $this->params->get('shopware.mail.update_mail_variables_on_send');

        if ($setting) {
            $result = SettingsResult::warning('mail_variables', 'MailVariables should not be updated frequently');
            $result->current = 'enabled';
        }

        $result->recommended = 'disabled';
        $result->url = 'https://developer.shopware.com/docs/guides/hosting/performance/performance-tweaks#prevent-mail-data-updates';
        $collection->add($result);
    }
}
