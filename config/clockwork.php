<?php

declare(strict_types=1);

return [
    // Debug instrumentation must be explicitly enabled. This keeps Clockwork
    // inactive in production even if APP_DEBUG is accidentally misconfigured.
    'enable' => env('CLOCKWORK_ENABLE', false),
];
