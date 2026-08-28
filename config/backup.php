<?php

return [
    'disk' => env('BACKUP_DISK', 'local'),
    'directory' => env('BACKUP_DIRECTORY', 'backups/database'),
    'retention' => (int) env('BACKUP_RETENTION', 20),
    'max_upload_kb' => (int) env('BACKUP_MAX_UPLOAD_KB', 102400),
    'dump_binary' => env('MYSQLDUMP_BINARY', 'mysqldump'),
    'restore_binary' => env('MYSQL_BINARY', 'mysql'),
    'timeout' => (int) env('BACKUP_TIMEOUT', 900),
];
