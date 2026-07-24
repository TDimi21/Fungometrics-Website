<?php

declare(strict_types=1);

return [
    'max_file_size_bytes' => (int) env('DATA_HUB_MAX_FILE_SIZE_BYTES', 25 * 1024 * 1024),
    'extensions' => ['csv', 'xlsx'],
    'mime_types' => [
        'csv' => [
            'text/csv',
            'text/plain',
            'application/csv',
            'application/vnd.ms-excel',
        ],
        'xlsx' => [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/zip',
        ],
    ],
];

