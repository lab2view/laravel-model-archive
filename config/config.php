<?php

return [
    'main_db_connection' => env('DB_CONNECTION', 'mysql'),
    'archive_db_connection' => env('ARCHIVE_DB_CONNECTION', 'archive'),
    'archive_delete_from_main' => env('ARCHIVE_DELETE_FROM_MAIN', true),
    'between_commands' => [
        'all' => [],
        'before' => [
            'all' => [],
            'lab2view:model_archive' => [],
            'lab2view:validate_model_archive' => [],
        ],
        'after' => [
            'all' => [],
            'lab2view:model_archive' => [],
            'lab2view:validate_model_archive' => [],
        ],
    ],
];
