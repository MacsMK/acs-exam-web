<?php

declare(strict_types=1);

namespace App\Support;

final class AcademicNumber
{
    public const INTEGER = 'regex:/\A[0-9]+\z/';

    public const DECIMAL = 'regex:/\A[0-9]+(?:\.[0-9]{1,2})?\z/';

    public static function normalize(mixed $value): mixed
    {
        return is_string($value) ? str_replace(',', '.', trim($value)) : $value;
    }

    public static function forInput(mixed $value): mixed
    {
        if (is_string($value) && preg_match('/\A[0-9]+\.[0-9]+\z/', $value) === 1) {
            return rtrim(rtrim($value, '0'), '.');
        }

        return $value;
    }
}
