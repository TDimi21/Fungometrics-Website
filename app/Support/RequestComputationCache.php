<?php

declare(strict_types=1);

namespace App\Support;

/** Reuse expensive read calculations only within an explicitly enabled GET request. */
final class RequestComputationCache
{
    public static function remember(string $key, callable $compute): array
    {
        $request = request();
        if (! $request->isMethod('GET') || ! $request->attributes->get('_fmtrx_reuse_intelligence', false)) {
            return $compute();
        }

        $key = '_fmtrx_computation_'.hash('sha256', $key);
        if ($request->attributes->has($key)) {
            return $request->attributes->get($key);
        }

        // Do not cache exceptions; nested computations may add their own entries.
        $result = $compute();
        $request->attributes->set($key, $result);
        return $result;
    }
}
