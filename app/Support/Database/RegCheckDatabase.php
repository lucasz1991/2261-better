<?php

namespace App\Support\Database;

use App\Models\Setting;
use Illuminate\Support\Facades\DB;

class RegCheckDatabase
{
    public static function connectionName(): string
    {
        $connection = env('ANALYTICS_DB_CONNECTION', 'mysql_analytics');
        $settings = Setting::getValue('database', 'config') ?? [];
        $settings = is_array($settings) ? $settings : [];
        $base = config("database.connections.{$connection}", config('database.connections.mysql'));

        config()->set("database.connections.{$connection}", array_merge($base, [
            'driver' => 'mysql',
            'host' => $settings['host'] ?? env('ANALYTICS_DB_HOST', env('DB_HOST', '127.0.0.1')),
            'port' => $settings['port'] ?? env('ANALYTICS_DB_PORT', env('DB_PORT', '3306')),
            'database' => $settings['database'] ?? env('ANALYTICS_DB_DATABASE', 'regulierungs-check'),
            'username' => $settings['username'] ?? env('ANALYTICS_DB_USERNAME', env('DB_USERNAME', 'root')),
            'password' => $settings['password'] ?? env('ANALYTICS_DB_PASSWORD', env('DB_PASSWORD', '')),
        ]));

        DB::purge($connection);

        return $connection;
    }
}
