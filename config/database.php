<?php
// config/database.php

function env_or_file(string $envVar, string $default = ''): string
{
    $fileVar = $envVar . '_FILE';
    if (!empty($_SERVER[$fileVar]) && is_readable($_SERVER[$fileVar])) {
        return trim(file_get_contents($_SERVER[$fileVar]));
    }
    return $_SERVER[$envVar] ?? getenv($envVar) ?: $default;
}

return [
    'host' => env_or_file('DB_HOST', '127.0.0.1'),
    'port' => (int) env_or_file('DB_PORT', '3306'),
    'dbname' => env_or_file('DB_NAME', 'portale_dipendenti'),
    'user' => env_or_file('DB_USER', 'root'),
    'pass' => env_or_file('DB_PASSWORD', ''),
];
