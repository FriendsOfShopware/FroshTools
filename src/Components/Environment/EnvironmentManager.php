<?php declare(strict_types=1);

namespace Frosh\Tools\Components\Environment;

class EnvironmentManager
{
    public function read(string $path): EnvironmentFile
    {
        $content = file_get_contents($path);
        if ($content === false) {
            throw new \RuntimeException(sprintf('Cannot read file %s', $path));
        }

        $lines = preg_split('/\r\n|\r|\n/', $content);
        $parsedLines = [];
        $lineCount = count($lines) - 1;
        foreach ($lines as $i => $line) {
            $line = trim($line);

            if ($line === '' || $line[0] === '#') {
                $parsedLines[] = EnvironmentCommentLine::parse($line);
                continue;
            }

            $kv = EnvironmentKeyValue::parse($line);
            $parsedLines[] = $kv;
        }

        if ($lineCount && $parsedLines[$lineCount]->getLine() === '') {
            unset($parsedLines[$lineCount]);
        }

        return new EnvironmentFile($parsedLines);
    }

    public function save(string $path, EnvironmentFile $file): void
    {
        file_put_contents($path, $file->__toString());
    }
}
