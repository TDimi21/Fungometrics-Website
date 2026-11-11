<?php

namespace App\Http\Requests\Concerns;

trait NormalizesTestPhone
{
    protected bool $testPhoneProvided = false;

    protected function normalizeTestPhone(): void
    {
        if (\App\Support\TestPhoneNumber::matches($this->input('phone'))) {
            $this->testPhoneProvided = true;
            // NULL keeps placeholders reusable without creating a shared login identity.
            $this->merge(['phone' => null]);
        }
    }
}
