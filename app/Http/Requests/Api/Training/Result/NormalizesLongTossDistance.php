<?php
namespace App\Http\Requests\Api\Training\Result;
trait NormalizesLongTossDistance
{
    protected function prepareForValidation(): void {
        $value=$this->input('distance');
        if($value===null || $value==='' || !is_numeric($value))return;
        $feet=(float)$value*($this->input('distance_unit','ft')==='yd'?3:1);
        // Check the unrounded value: rounding may never admit a throw over 500 ft.
        $this->merge(['distance'=>$feet>=0 && $feet<=500 ? (int)round($feet) : $feet]);
    }
}
