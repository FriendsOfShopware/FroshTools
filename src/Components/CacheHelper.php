<?php declare(strict_types=1);

namespace Frosh\Tools\Components;

use Frosh\Tools\Components\Exception\CannotClearCacheException;
use Symfony\Component\Process\Process;

class CacheHelper
{
    public static function getSize(string $dir): int
    {
        if (\is_file($dir)) {
            return \filesize($dir);
        }

        return self::getSizeFast($dir) ?? self::getSizeFallback($dir);
    }

    private static function getSizeFast(string $dir): ?int
    {
        $process = new Process(['du', '-s', $dir]);
        $process->run();

        if (!$process->isSuccessful()) {
            return null;
        }

        if (preg_match('/\d+/', $process->getOutput(), $match)) {
            return $match[0] * 1024;
        }

        return null;
    }

    private static function getSizeFallback(string $path): int
    {
        $dirIterator = new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS);
        $iterator = new \RecursiveIteratorIterator(
            $dirIterator,
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        $size = 0;

        /** @var \SplFileInfo $entry */
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

    public static function removeDir(string $path): void
    {
        // If the given path is a file
        if (is_file($path)) {
            unlink($path);

            return;
        }

        if (self::rsyncAvailable()) {
            $blankDir = sys_get_temp_dir() . '/' . uniqid() . '/';

            if (!mkdir($blankDir, 0755, true) && !is_dir($blankDir)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $blankDir));
            }

            $process = new Process(['rsync', '-qa', '--delete', $blankDir, $path . '/']);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new CannotClearCacheException($process->getErrorOutput());
            }

            rmdir($blankDir);
        } else {
            $process = new Process(['find', $path . '/', '-delete']);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new CannotClearCacheException($process->getErrorOutput());
            }
        }
    }

    private static function rsyncAvailable(): bool
    {
        $process = new Process(['rsync', '--version']);
        $process->run();

        return $process->isSuccessful();
    }
}
