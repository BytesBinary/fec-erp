<?php

namespace Database\Seeders\Testing;

/**
 * Builds small transparent PNG "signatures" without the GD extension, so
 * every approver of the test/demo data has a distinct, valid signature file.
 */
final class SignatureImage
{
    public static function png(int $seed, int $width = 160, int $height = 48): string
    {
        $rows = '';
        $color = [20 + ($seed * 53) % 120, 30 + ($seed * 97) % 100, 120 + ($seed * 31) % 120];

        for ($y = 0; $y < $height; $y++) {
            $rows .= "\0";

            for ($x = 0; $x < $width; $x++) {
                $wave = (int) round($height / 2 + sin(($x + $seed * 7) / (5 + $seed % 4)) * ($height / 3.2));
                $on = abs($y - $wave) <= 1 && $x > 8 && $x < $width - 8;
                $rows .= $on ? chr($color[0]).chr($color[1]).chr($color[2]).chr(255) : "\0\0\0\0";
            }
        }

        return "\x89PNG\r\n\x1a\n"
            .self::chunk('IHDR', pack('NNCCCCC', $width, $height, 8, 6, 0, 0, 0))
            .self::chunk('IDAT', (string) gzcompress($rows, 9))
            .self::chunk('IEND', '');
    }

    protected static function chunk(string $type, string $data): string
    {
        return pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
    }
}
