<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Log;

/**
 * Original FroshTools parser — PCRE LINE_MATCH.
 *
 * @see \Frosh\Tools\Controller\LogController
 */
final class PregMonologParser implements MonologLineParserInterface
{
    // https://regex101.com/r/bp4YYL/1
    public const LINE_MATCH = '/\[(?<date>.*)] (?<channel>.*)\.(?<level>(DEBUG|INFO|NOTICE|WARNING|ERROR|CRITICAL|ALERT|EMERGENCY)):(?<message>.*)/m';

    public function parse(string $line): array
    {
        if (preg_match(self::LINE_MATCH, $line, $matches) !== 1) {
            return [
                'message' => $line,
                'channel' => 'unknown',
                'date' => 'unknown',
                'level' => 'unknown',
            ];
        }

        return [
            'message' => $matches['message'],
            'channel' => $matches['channel'],
            'date' => $matches['date'],
            'level' => $matches['level'],
        ];
    }

    public function backend(): string
    {
        return 'preg';
    }
}
