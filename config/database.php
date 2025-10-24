<?php
/**
 * 数据库配置
 * 支持 MySQL, SQLite, PostgreSQL
 * 
 * 直接修改下面的配置或者通过设置系统环境变量
 */

return [
    // 默认数据库连接 (使用SQLite便于快速开始)
    'default' => getenv('DB_CONNECTION') ?: 'sqlite',

    // 数据库连接配置
    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'host' => getenv('DB_HOST') ?: '127.0.0.1',
            'port' => getenv('DB_PORT') ?: 3306,
            'database' => getenv('DB_DATABASE') ?: 'wind_oauth',
            'username' => getenv('DB_USERNAME') ?: 'root',
            'password' => getenv('DB_PASSWORD') ?: '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
        ],

        'sqlite' => [
            'driver' => 'sqlite',
            'database' => getenv('DB_DATABASE') ?: (runtime_path() . '/database.sqlite'),
            'prefix' => '',
            'foreign_key_constraints' => true,
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'host' => getenv('DB_HOST') ?: '127.0.0.1',
            'port' => getenv('DB_PORT') ?: 5432,
            'database' => getenv('DB_DATABASE') ?: 'wind_oauth',
            'username' => getenv('DB_USERNAME') ?: 'postgres',
            'password' => getenv('DB_PASSWORD') ?: '',
            'charset' => 'utf8',
            'prefix' => '',
            'schema' => 'public',
            'sslmode' => 'prefer',
        ],
    ],
];
