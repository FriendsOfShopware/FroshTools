<?php declare(strict_types=1);

namespace Frosh\Tools\Components;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class CacheHelper
{
    public static function getSize(string $dir): int
    {
        return self::getSizeFast($dir) ?? self::getSizeFallback($dir);
    }

    private function getSizeFast(string $dir): ?int
    {
        $output = null;
        exec('du -s "' . $dir . '"', $output);
        if (preg_match('/[0-9]+/', $output[0], $match)) {
            return $match[0] * 1024;
        }

        return null;
    }

    private function getSizeFallback(string $path): int
    {
        $dirIterator = new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS);
        $iterator = new RecursiveIteratorIterator(
            $dirIterator,
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        $size = 0;

        /** @var SplFileInfo $entry */
        foreach ($iterator as $entry) {
            if ($entry->getFilename() === '.gitkeep') {
                continue;
            }

            if (!$entry->isFile()) {
                continue;
            }

            $size += $entry->getSize();
        }

        return $size;
    }

    public static function removeDir(string $dir): void
    {
        if (self::rsyncAvailable()) {
            $blankDir = sys_get_temp_dir() . '/' . md5($dir . time()) . '/';

            if (!mkdir($blankDir, 0777, true) && !is_dir($blankDir)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $blankDir));
            }

            exec('rsync -a --delete ' . $blankDir . ' ' . $dir . '/');
            rmdir($blankDir);
        } else {
            exec('find ' . $dir . '/ -delete');
        }
    }

    private static function rsyncAvailable(): bool
    {
        $output = null;
        exec('command -v rsync', $output);

        return count($output) > 0;
    }
}
