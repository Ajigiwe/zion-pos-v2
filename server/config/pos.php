<?php

return [
    // Configured till identity, stamped on sales for audit and reporting
    // (replaces the Flutter WorkstationConfig / LAN sync identity).
    'till_id' => env('POS_TILL_ID', 'Web-Till-1'),

    // mysqldump binary used by the backup card and `pos:backup`. When the
    // binary is missing or exec() is blocked on the host, the backup service
    // automatically falls back to a pure-PHP dump — no configuration needed
    // either way on shared cPanel.
    'mysqldump' => env(
        'MYSQLDUMP_PATH',
        PHP_OS_FAMILY === 'Windows' ? 'C:\xampp\mysql\bin\mysqldump.exe' : '/usr/bin/mysqldump',
    ),

    // mysql client used by the restore flow (restore needs it; download a
    // generated dump if exec() is unavailable).
    'mysql' => env('MYSQL_PATH', PHP_OS_FAMILY === 'Windows' ? 'C:\xampp\mysql\bin\mysql.exe' : '/usr/bin/mysql'),
];
