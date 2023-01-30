<?php declare(strict_types=1);

namespace Frosh\Tools\Components;

class Planuml
{
    public static function encodep(string $puml): string
    {
        $compressed = gzdeflate($puml, 9);

        if (false === $compressed) {
            throw new \RuntimeException('Error while compressing PlantUml diagram.');
        }

        return self::encode64($compressed);
    }

    public static function encode64(string $compressed): string
    {
        $encoded = '';
        $length = mb_strlen($compressed, '8bit');
        for ($i = 0; $i < $length; $i += 3) {
            switch ($length) {
                case $i + 1:
                    $encoded .= self::append3bytes(ord($compressed[$i]), 0, 0);
                    break;
                case $i + 2:
                    $encoded .= self::append3bytes(ord($compressed[$i]), ord($compressed[$i + 1]), 0);
                    break;
                default:
                    $encoded .= self::append3bytes(ord($compressed[$i]), ord($compressed[$i + 1]), ord($compressed[$i + 2]));
                    break;
            }
        }

        return $encoded;
    }

    public static function append3bytes(int $b1, int $b2, int $b3): string
    {
        $c1 = $b1 >> 2;
        $c2 = (($b1 & 0x3) << 4) | ($b2 >> 4);
        $c3 = (($b2 & 0xF) << 2) | ($b3 >> 6);
        $c4 = $b3 & 0x3F;
        $r = self::encode6bit($c1 & 0x3F);
        $r .= self::encode6bit($c2 & 0x3F);
        $r .= self::encode6bit($c3 & 0x3F);
        $r .= self::encode6bit($c4 & 0x3F);

        return $r;
    }

    public static function encode6bit(int $b): string
    {
        if ($b < 10) {
            return chr(48 + $b);
        }
        $b -= 10;
        if ($b < 26) {
            return chr(65 + $b);
        }
        $b -= 26;
        if ($b < 26) {
            return chr(97 + $b);
        }
        $b -= 26;
        if ($b === 0) {
            return '-';
        }
        if ($b === 1) {
            return '_';
        }

        return '?';
    }
}
