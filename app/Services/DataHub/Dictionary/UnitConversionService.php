<?php

declare(strict_types=1);

namespace App\Services\DataHub\Dictionary;

use InvalidArgumentException;

final class UnitConversionService
{
    public function convert(float $value, string $source, string $target): float
    {
        if($source === $target) {
            return $value;
        }return match("{$source}:{$target}") {
            'kph:mph' => $value * 0.621371,'mph:kph' => $value / 0.621371,'m:ft' => $value * 3.28084,'ft:m' => $value / 3.28084,'cm:in' => $value * 0.393701,'in:cm' => $value / 0.393701,'kg:lbs' => $value * 2.20462,'lbs:kg' => $value / 2.20462,default => throw new InvalidArgumentException('No approved unit conversion exists.')
        };
    } public function defaultSystem(?string $country): string
    {
        return in_array(mb_strtoupper((string)$country), ['US','USA'], true) ? 'imperial' : 'metric';
    }
}
