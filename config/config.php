<?php

return [
    'main_db_connection' => env('DB_CONNECTION', 'mysql'),
    'archive_db_connection' => env('ARCHIVE_DB_CONNECTION', 'archive'),
    'archive_delete_from_main' => env('ARCHIVE_DELETE_FROM_MAIN', true),
];
