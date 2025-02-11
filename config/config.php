<?php

return [
    'main_db_connection' => env('DB_CONNECTION', 'mysql'),
    'archive_db_connection' => env('ARCHIVE_DB_CONNECTION', 'archive'),
    'archive_delete_from_main' => env('ARCHIVE_DELETE_FROM_MAIN', true),
    'between_commands' => [
        'all' => [],
        'before' => [
            'all' => [],
            'lab2view:archive-model' => [],
            'lab2view:validate-archive-model' => [],
        ],
        'after' => [
            'all' => [],
            'lab2view:archive-model' => [],
            'lab2view:validate-archive-model' => [],
        ],
    ],
];
