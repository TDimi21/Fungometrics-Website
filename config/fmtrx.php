<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Cage Distance Model v2
    |--------------------------------------------------------------------------
    | When enabled, CageDistanceService runs alongside the existing
    | client-supplied distance_travel on every cage result save, storing its
    | output in the nullable estimated_carry_v2/etc. columns without
    | replacing or altering distance_travel or Cage Score. See
    | app/Services/Cage/CageDistanceService.php.
    */
    'cage_distance_v2_enabled' => env('FMTRX_CAGE_DISTANCE_V2_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Cage Distance Validation Lab
    |--------------------------------------------------------------------------
    | Gates the dev/admin-only POST /api/admin/cage-distance/validate preview
    | endpoint (see CageDistanceValidationService / CageDistanceValidationController).
    | Defaults to disabled everywhere, including production, until explicitly
    | turned on.
    */
    'cage_distance_validation_enabled' => env('CAGE_DISTANCE_VALIDATION_ENABLED', false),
];
