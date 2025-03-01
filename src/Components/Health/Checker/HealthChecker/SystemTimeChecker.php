<?php

declare (strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\HealthChecker;

use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class SystemTimeChecker implements HealthCheckerInterface, CheckerInterface
{
    public function collect(HealthCollection $collection): void
    {
        $this->checkSystemTime($collection);
    }

    private function checkSystemTime(HealthCollection $collection): void
    {
        $url = 'https://cloudflare.com/cdn-cgi/trace';
        $snippet = 'System time';
        $recommended = 'max 5 seconds';

        try {
            $response = (new Client())->request('GET', $url);

            $data = [];
            $lines = explode("\n", trim((string) $response->getBody()));
            foreach ($lines as $line) {
                if (str_contains($line, '=')) {
                    [$key, $value] = explode("=", $line, 2);
                    $data[$key] = $value;
                }
            }

            $cloudflareTimestamp = isset($data['ts']) ? (int) $data['ts'] : null;
            if (!$cloudflareTimestamp) {
                $status = SettingsResult::info('system-time', $snippet, 'Could not parse remote time', $recommended, $url);
                $collection->add($status);
                return;
            }

            $diff = abs(time() - $cloudflareTimestamp);
            if ($diff > 5) {
                $status = SettingsResult::warning('system-time', $snippet, $diff . ' seconds', $recommended);
            } else {
                $status = SettingsResult::ok('system-time', $snippet, $diff . ' second(s)', $recommended);
            }
        } catch (GuzzleException) {
            $status = SettingsResult::info('system-time', $snippet, 'Could not fetch remote time', $recommended, $url);
        }

        $collection->add($status);
    }
}
