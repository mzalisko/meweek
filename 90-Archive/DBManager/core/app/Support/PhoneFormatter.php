<?php

namespace App\Support;

class PhoneFormatter
{
    public static function format(?string $number, ?string $pattern): ?string
    {
        $number = trim((string) $number);
        $pattern = trim((string) $pattern);

        if ($number === '' || $pattern === '') {
            return $number !== '' ? $number : null;
        }

        $digits = preg_replace('/\D+/', '', $number) ?? '';
        if ($digits === '' || ! str_contains($pattern, '#')) {
            return $number;
        }

        $result = '';
        $index = 0;
        $length = strlen($digits);

        foreach (str_split($pattern) as $char) {
            if ($char !== '#') {
                $result .= $char;
                continue;
            }

            if ($index >= $length) {
                break;
            }

            $result .= $digits[$index++];
        }

        if ($index < $length) {
            $result .= substr($digits, $index);
        }

        return trim($result);
    }

    public static function isValidPattern(string $pattern): bool
    {
        $pattern = trim($pattern);

        return $pattern === '' || (strlen($pattern) <= 80 && preg_match('/^[#\s+\-().]+$/', $pattern) === 1 && str_contains($pattern, '#'));
    }
}
