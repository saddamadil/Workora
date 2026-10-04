<?php

return [

    // Largest single file accepted, in megabytes. PHP's own upload_max_filesize and
    // post_max_size must be at least this high too, or PHP rejects the request first.
    'max_upload_mb' => (int) env('WORKORA_MAX_UPLOAD_MB', 100),

    // Disk that holds uploaded files. Private: nothing is served by guessable URL.
    'disk' => env('WORKORA_DISK', 'local'),

    // Extensions that are never accepted, whatever the MIME type claims.
    'blocked_extensions' => [
        'php', 'phtml', 'phar', 'exe', 'msi', 'bat', 'cmd', 'com', 'scr', 'sh', 'js', 'jar', 'vbs', 'ps1', 'dll',
    ],

    // The one person who can change workspaces' plans. Set PLATFORM_ADMIN_EMAIL in .env.
    'platform_admin_email' => env('PLATFORM_ADMIN_EMAIL'),

    'folders' => ['Documents', 'Images', 'Designs', 'Deliverables', 'Contracts', 'Other'],
];
