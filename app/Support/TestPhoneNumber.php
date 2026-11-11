<?php

namespace App\Support;

final class TestPhoneNumber
{
    public static function matches($value): bool
    {
        return is_string($value) && preg_match('/^[01+\s().-]+$/', $value)
            && in_array(preg_replace('/\D/', '', $value), ['0000000000', '10000000000'], true);
    }
}
