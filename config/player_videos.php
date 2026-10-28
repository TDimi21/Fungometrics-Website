<?php
return [
    // Requires ffprobe (FFmpeg) on every upload-serving host.
    'ffprobe_binary' => env('FFPROBE_BINARY', 'ffprobe'),
];
