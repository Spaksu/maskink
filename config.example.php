<?php
// Maskink configuration – database connection only.
// Copy this file to config.php and fill in your details.
// The database and tables are created automatically on the first request; there is no other setup step.
return [
    'db' => [
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'name'    => 'maskink',
        'user'    => 'USER',
        'pass'    => 'PASSWORD',
    ],
    // AI connections (address, API key, models) are managed under Settings → AI connections.
];
