<?php

declare(strict_types=1);

namespace App\Support;

final class Utf8
{
    public static function sanitize(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        $converted = @iconv('UTF-8', 'UTF-8//IGNORE', $value);

        if ($converted !== false) {
            return $converted;
        }

        return preg_replace('/[^\x09\x0A\x0D\x20-\x7E]/', '', $value) ?? '';
    }

    public static function sanitizeMixed(mixed $value): mixed
    {
        if (is_string($value)) {
            return self::sanitize($value);
        }

        if (is_array($value)) {
            return array_map(self::sanitizeMixed(...), $value);
        }

        return $value;
    }

    public static function toAscii(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        return preg_replace('/[^A-Za-z0-9\-_]/', '', self::sanitize($value) ?? '') ?: null;
    }
}
