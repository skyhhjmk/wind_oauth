<?php
namespace app\bootstrap;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher;
use Illuminate\Container\Container;
use Webman\Bootstrap;

/**
 * Laravel ORM 数据库引导
 */
class Database implements Bootstrap
{
    /**
     * @param \Workerman\Worker $worker
     * @return void
     */
    public static function start($worker)
    {
        $config = config('database');
        $capsule = new Capsule();

        $connections = $config['connections'] ?? [];
        $default = $config['default'] ?? 'mysql';

        // 如果使用SQLite，自动初始化数据库
        if ($default === 'sqlite' && isset($connections['sqlite'])) {
            $dbPath = $connections['sqlite']['database'];
            if (!file_exists($dbPath)) {
                self::initSQLiteDatabase($dbPath);
            }
        }

        foreach ($connections as $name => $connection) {
            $capsule->addConnection($connection, $name);
        }

        $capsule->setAsGlobal();
        $capsule->setEventDispatcher(new Dispatcher(new Container()));
        $capsule->bootEloquent();

        // 设置默认连接
        $capsule->getDatabaseManager()->setDefaultConnection($default);
    }

    /**
     * 初始化SQLite数据库
     */
    private static function initSQLiteDatabase($dbPath)
    {
        try {
            // 创建数据库文件
            touch($dbPath);
            
            $pdo = new \PDO('sqlite:' . $dbPath);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            
            // 检查表是否已存在
            $result = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'");
            if ($result->fetch()) {
                return; // 表已存在
            }
            
            // 读取并执行SQL脚本
            $sqlFile = base_path() . '/app/database/sqlite_schema.sql';
            if (file_exists($sqlFile)) {
                $sql = file_get_contents($sqlFile);
                $pdo->exec($sql);
                echo "[OAuth] SQLite database initialized successfully!\n";
            }
        } catch (\Exception $e) {
            echo "[OAuth] Failed to initialize SQLite database: " . $e->getMessage() . "\n";
        }
    }
}
