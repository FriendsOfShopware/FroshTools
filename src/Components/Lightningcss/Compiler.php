<?php

namespace Frosh\Tools\Components\Lightningcss;

use Psr\Log\LoggerInterface;

class Compiler
{
    private static string $apiUrl = 'https://27uhytumuulrysydgmak3tlsgu0giwff.lambda-url.eu-central-1.on.aws';
    private static array $browserlist = [];
    private static LoggerInterface $logger;

    private string $cssCode;

    public function __construct(string $cssCode)
    {
        $this->cssCode = $cssCode;
    }

    public function compile(bool $debug): string
    {
        $ch = curl_init(self::$apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'stylesheet' => $this->cssCode,
            'browserlist' => implode("\n", self::$browserlist),
            'minify' => !$debug,
        ], JSON_THROW_ON_ERROR));

        $response = curl_exec($ch);

        curl_close($ch);

        if ($response === false) {
            self::$logger->critical('Could not connect to lightningcss api');
            return $this->cssCode;
        }

        if (curl_getinfo($ch, CURLINFO_HTTP_CODE) !== 200) {
            self::$logger->critical('CSS transform failed: ' . $response);
            return $this->cssCode;
        }

        /** @var array{compiled: string} $data */
        $data = json_decode($response, true, 512, \JSON_THROW_ON_ERROR);

        return $data['compiled'];
    }

    public static function setApiURL(string $apiUrl): void
    {
        self::$apiUrl = $apiUrl;
    }

    public static function setBrowserList(array $browserlist): void
    {
        self::$browserlist = $browserlist;
    }

    public static function setLogger(LoggerInterface $logger)
    {
        self::$logger = $logger;
    }
}
