<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Log;

/**
 * Resolves the prebuilt libmonolog_parser shared library for this OS/CPU/libc.
 *
 * Shipped artifacts (users never compile):
 *
 *   libmonolog_parser-linux-x86_64.so          (glibc)
 *   libmonolog_parser-linux-arm64.so           (glibc)
 *   libmonolog_parser-linux-musl-x86_64.so     (Alpine / musl)
 *   libmonolog_parser-linux-musl-arm64.so      (Alpine / musl)
 *   libmonolog_parser-darwin-x86_64.dylib
 *   libmonolog_parser-darwin-arm64.dylib
 *
 * Override library:  MONOLOG_PARSER_LIB=/absolute/path
 * Override libc:     MONOLOG_PARSER_LIBC=musl|gnu
 */
final class NativeLibraryLocator
{
    /**
     * Absolute path to a readable library, or null if none match this platform.
     */
    public static function locate(?string $pluginRoot = null): ?string
    {
        $env = getenv('MONOLOG_PARSER_LIB');
        if (\is_string($env) && $env !== '' && is_readable($env)) {
            return $env;
        }

        $root = $pluginRoot ?? \dirname(__DIR__, 3);
        $libDir = $root . '/native/lib';

        foreach (self::candidateBasenames() as $basename) {
            $path = $libDir . '/' . $basename;
            if (is_readable($path)) {
                return $path;
            }
        }

        // Dev tree: `make` drops a generic name next to sources
        $dev = [
            $root . '/native/monolog_parser/libmonolog_parser.so',
            $root . '/native/monolog_parser/libmonolog_parser.dylib',
            $libDir . '/libmonolog_parser.so',
            $libDir . '/libmonolog_parser.dylib',
        ];
        foreach ($dev as $path) {
            if (is_readable($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Platform info used in artifact filenames.
     *
     * @return array{os: string, arch: string, libc: string, ext: string, triple: string}
     */
    public static function platform(): array
    {
        $osFamily = \PHP_OS_FAMILY;
        $rawArch = php_uname('m');

        $os = match ($osFamily) {
            'Darwin' => 'darwin',
            'Linux' => 'linux',
            default => strtolower($osFamily),
        };

        $arch = match (true) {
            \in_array($rawArch, ['x86_64', 'amd64', 'x64'], true) => 'x86_64',
            \in_array($rawArch, ['aarch64', 'arm64'], true) => 'arm64',
            default => $rawArch,
        };

        $ext = match ($os) {
            'darwin' => 'dylib',
            'windows' => 'dll',
            default => 'so',
        };

        // libc only meaningful on Linux (gnu vs musl). Darwin/Windows → "n/a".
        $libc = $os === 'linux' ? (self::isMusl() ? 'musl' : 'gnu') : 'n/a';

        $triple = $os === 'linux' && $libc === 'musl'
            ? \sprintf('linux-musl-%s', $arch)
            : \sprintf('%s-%s', $os, $arch);

        return [
            'os' => $os,
            'arch' => $arch,
            'libc' => $libc,
            'ext' => $ext,
            'triple' => $triple,
        ];
    }

    /**
     * Detect musl (Alpine, void, etc.).
     *
     * Order:
     *  1. MONOLOG_PARSER_LIBC=musl|gnu|glibc
     *  2. Presence of /lib/ld-musl-*.so.1
     *  3. ldd on PHP_BINARY mentions musl
     *  4. phpinfo() / PHP_OS extras (last resort)
     */
    public static function isMusl(): bool
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $env = getenv('MONOLOG_PARSER_LIBC');
        if (\is_string($env) && $env !== '') {
            $v = strtolower($env);
            if (\in_array($v, ['musl'], true)) {
                return $cached = true;
            }
            if (\in_array($v, ['gnu', 'glibc', 'gcompat'], true)) {
                return $cached = false;
            }
        }

        if (\PHP_OS_FAMILY !== 'Linux') {
            return $cached = false;
        }

        // Canonical musl dynamic linker paths (Alpine multi-arch)
        $loaders = [
            '/lib/ld-musl-x86_64.so.1',
            '/lib/ld-musl-aarch64.so.1',
            '/lib/ld-musl-armhf.so.1',
            '/lib/ld-musl-i386.so.1',
            '/lib/ld-musl-riscv64.so.1',
        ];
        foreach ($loaders as $loader) {
            if (is_readable($loader)) {
                return $cached = true;
            }
        }

        // Glob fallback (some layouts)
        foreach (glob('/lib/ld-musl-*.so.*') ?: [] as $loader) {
            if (is_readable($loader)) {
                return $cached = true;
            }
        }

        // Inspect what PHP itself is linked against
        if (is_readable(\PHP_BINARY)) {
            $ldd = self::safeLdd(\PHP_BINARY);
            if ($ldd !== null) {
                if (str_contains($ldd, 'musl')) {
                    return $cached = true;
                }
                // Explicit glibc markers
                if (str_contains($ldd, 'libc.so.6') || str_contains($ldd, 'ld-linux')) {
                    return $cached = false;
                }
            }
        }

        return $cached = false;
    }

    /**
     * Preferred basenames, most specific first.
     *
     * @return list<string>
     */
    public static function candidateBasenames(): array
    {
        $p = self::platform();
        $names = [];

        if ($p['os'] === 'linux' && $p['libc'] === 'musl') {
            // musl hosts must not load a glibc .so (hard ELF failure)
            $names[] = \sprintf('libmonolog_parser-linux-musl-%s.so', $p['arch']);
            if ($p['arch'] === 'arm64') {
                $names[] = 'libmonolog_parser-linux-musl-aarch64.so';
            }
        } elseif ($p['os'] === 'linux') {
            $names[] = \sprintf('libmonolog_parser-linux-%s.so', $p['arch']);
            if ($p['arch'] === 'arm64') {
                $names[] = 'libmonolog_parser-linux-aarch64.so';
            }
        // Do not fall back to musl on glibc — wrong ABI
        } else {
            $names[] = \sprintf('libmonolog_parser-%s-%s.%s', $p['os'], $p['arch'], $p['ext']);
        }

        // Legacy unprefixed names (local `make install`)
        $names[] = 'libmonolog_parser.' . $p['ext'];

        return $names;
    }

    /**
     * Human-readable platform string for diagnostics.
     */
    public static function describe(): string
    {
        $p = self::platform();
        $candidates = self::candidateBasenames();

        return \sprintf(
            '%s libc=%s (php_uname=%s, looking for %s)',
            $p['triple'],
            $p['libc'],
            php_uname('m'),
            $candidates[0] ?? '?'
        );
    }

    private static function safeLdd(string $binary): ?string
    {
        // Avoid shell if disabled; skip in restricted SAPIs
        if (!\function_exists('shell_exec')) {
            return null;
        }

        // open_basedir / disabled functions may still kill this
        try {
            $cmd = 'ldd ' . escapeshellarg($binary) . ' 2>&1';
            $out = @shell_exec($cmd);
        } catch (\Throwable) {
            return null;
        }

        return \is_string($out) ? $out : null;
    }
}
