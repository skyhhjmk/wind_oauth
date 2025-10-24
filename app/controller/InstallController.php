<?php

namespace app\controller;

use support\Request;
use support\Response;
use Dotenv\Dotenv;

class InstallController
{
    /**
     * 显示安装页面
     */
    public function index(Request $request): Response
    {
        // 检查是否已安装
        if ($this->isInstalled()) {
            return redirect('/');
        }

        return view('install', [
            'error' => $request->session()->get('error'),
            'success' => $request->session()->get('success'),
        ]);
    }

    /**
     * 处理安装提交
     */
    public function install(Request $request): Response
    {
        // 检查是否已安装
        if ($this->isInstalled()) {
            return redirect('/');
        }

        $dbType = $request->post('db_type', 'mysql');
        $dbHost = $request->post('db_host', '127.0.0.1');
        $dbPort = $request->post('db_port', '3306');
        $dbName = $request->post('db_name', 'wind_oauth');
        $dbUser = $request->post('db_user', 'root');
        $dbPassword = $request->post('db_password', '');

        // 管理员账户信息
        $adminUsername = $request->post('admin_username', 'admin');
        $adminEmail = $request->post('admin_email', 'admin@example.com');
        $adminPassword = $request->post('admin_password');
        $adminPasswordConfirm = $request->post('admin_password_confirm');

        // 验证管理员信息
        if (empty($adminUsername) || empty($adminEmail) || empty($adminPassword)) {
            $request->session()->set('error', '请填写完整的管理员信息');
            return redirect('/install');
        }

        if ($adminPassword !== $adminPasswordConfirm) {
            $request->session()->set('error', '两次密码输入不一致');
            return redirect('/install');
        }

        if (strlen($adminPassword) < 6) {
            $request->session()->set('error', '密码至少需要6个字符');
            return redirect('/install');
        }

        // 验证数据库连接
        try {
            $this->testDatabaseConnection($dbType, $dbHost, $dbPort, $dbName, $dbUser, $dbPassword);
        } catch (\Exception $e) {
            $request->session()->set('error', '数据库连接失败: ' . $e->getMessage());
            return redirect('/install');
        }

        // 创建 .env 文件
        try {
            $this->createEnvFile($dbType, $dbHost, $dbPort, $dbName, $dbUser, $dbPassword);
        } catch (\Exception $e) {
            $request->session()->set('error', '创建配置文件失败: ' . $e->getMessage());
            return redirect('/install');
        }

        // 运行数据库迁移
        try {
            $this->runMigrations($dbType, $dbHost, $dbPort, $dbName, $dbUser, $dbPassword, $adminUsername, $adminEmail, $adminPassword);
        } catch (\Exception $e) {
            $request->session()->set('error', '数据库初始化失败: ' . $e->getMessage());
            return redirect('/install');
        }

        // 创建 install.lock 文件
        try {
            $this->createLockFile();
        } catch (\Exception $e) {
            $request->session()->set('error', '创建锁定文件失败: ' . $e->getMessage());
            return redirect('/install');
        }

        $request->session()->set('success', '安装成功！');
        return redirect('/');
    }

    /**
     * 检查是否已安装
     */
    private function isInstalled(): bool
    {
        return file_exists(base_path() . '/install.lock');
    }

    /**
     * 删除所有表
     */
    private function dropAllTables(\PDO $pdo, string $dbType): void
    {
        try {
            if ($dbType === 'sqlite') {
                // SQLite: 禁用外键约束后删除
                $pdo->exec('PRAGMA foreign_keys = OFF');
                $tables = ['oauth_tokens', 'oauth_authorization_codes', 'oauth_scopes', 'oauth_clients', 'users'];
                foreach ($tables as $table) {
                    $pdo->exec("DROP TABLE IF EXISTS {$table}");
                }
                $pdo->exec('PRAGMA foreign_keys = ON');
            } elseif ($dbType === 'mysql') {
                // MySQL: 禁用外键检查后删除
                $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
                $tables = ['oauth_tokens', 'oauth_authorization_codes', 'oauth_scopes', 'oauth_clients', 'users'];
                foreach ($tables as $table) {
                    $pdo->exec("DROP TABLE IF EXISTS {$table}");
                }
                $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
            } elseif ($dbType === 'pgsql') {
                // PostgreSQL: 使用 CASCADE 删除
                $tables = ['oauth_tokens', 'oauth_authorization_codes', 'oauth_scopes', 'oauth_clients', 'users'];
                foreach ($tables as $table) {
                    $pdo->exec("DROP TABLE IF EXISTS {$table} CASCADE");
                }
            }
        } catch (\Exception $e) {
            // 如果删除失败，忽略错误，继续创建
        }
    }

    /**
     * 测试数据库连接
     */
    private function testDatabaseConnection(string $dbType, string $dbHost, string $dbPort, string $dbName, string $dbUser, string $dbPassword): void
    {
        if ($dbType === 'mysql') {
            $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
            $pdo = new \PDO($dsn, $dbUser, $dbPassword);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        } elseif ($dbType === 'pgsql') {
            $dsn = "pgsql:host={$dbHost};port={$dbPort};dbname={$dbName}";
            $pdo = new \PDO($dsn, $dbUser, $dbPassword);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        } elseif ($dbType === 'sqlite') {
            $dsn = "sqlite:{$dbName}";
            $pdo = new \PDO($dsn);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        } else {
            throw new \Exception('不支持的数据库类型');
        }
    }

    /**
     * 创建 .env 文件
     */
    private function createEnvFile(string $dbType, string $dbHost, string $dbPort, string $dbName, string $dbUser, string $dbPassword): void
    {
        $envPath = base_path() . '/.env';
        
        $envContent = <<<ENV
# 数据库配置
DB_CONNECTION={$dbType}
DB_HOST={$dbHost}
DB_PORT={$dbPort}
DB_DATABASE={$dbName}
DB_USERNAME={$dbUser}
DB_PASSWORD={$dbPassword}

# 应用配置
APP_DEBUG=true
APP_TIMEZONE=Asia/Shanghai

ENV;

        if (file_put_contents($envPath, $envContent) === false) {
            throw new \Exception('无法写入 .env 文件');
        }

        // 加载新的环境变量
        $dotenv = Dotenv::createImmutable(base_path());
        $dotenv->load();
    }

    /**
     * 运行数据库迁移
     */
    private function runMigrations(string $dbType, string $dbHost, string $dbPort, string $dbName, string $dbUser, string $dbPassword, string $adminUsername, string $adminEmail, string $adminPassword): void
    {
        if ($dbType === 'mysql') {
            $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
            $pdo = new \PDO($dsn, $dbUser, $dbPassword);
        } elseif ($dbType === 'pgsql') {
            $dsn = "pgsql:host={$dbHost};port={$dbPort};dbname={$dbName}";
            $pdo = new \PDO($dsn, $dbUser, $dbPassword);
        } elseif ($dbType === 'sqlite') {
            $dsn = "sqlite:{$dbName}";
            $pdo = new \PDO($dsn);
        } else {
            throw new \Exception('不支持的数据库类型');
        }

        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        // 先删除所有表（如果存在）
        $this->dropAllTables($pdo, $dbType);

        // 创建用户表
        $pdo->exec($this->getUsersTableSql($dbType));
        
        // 创建 OAuth 客户端表
        $pdo->exec($this->getOAuthClientsTableSql($dbType));
        
        // 创建 OAuth 授权码表
        $pdo->exec($this->getOAuthAuthorizationCodesTableSql($dbType));
        
        // 创建 OAuth 令牌表
        $pdo->exec($this->getOAuthTokensTableSql($dbType));
        
        // 创建 OAuth 权限范围表
        $pdo->exec($this->getOAuthScopesTableSql($dbType));

        // 插入默认权限范围
        $this->insertDefaultScopes($pdo);

        // 创建管理员账户
        $this->createAdminUser($pdo, $adminUsername, $adminEmail, $adminPassword);
    }

    /**
     * 获取用户表 SQL
     */
    private function getUsersTableSql(string $dbType): string
    {
        if ($dbType === 'sqlite') {
            return "CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE,
                email TEXT NOT NULL UNIQUE,
                password TEXT NOT NULL,
                name TEXT,
                is_admin INTEGER DEFAULT 0,
                status INTEGER DEFAULT 1,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )";
        } else {
            return "CREATE TABLE IF NOT EXISTS users (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(255) NOT NULL UNIQUE,
                email VARCHAR(255) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                name VARCHAR(255),
                is_admin TINYINT(1) DEFAULT 0,
                status TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )";
        }
    }

    /**
     * 获取 OAuth 客户端表 SQL
     */
    private function getOAuthClientsTableSql(string $dbType): string
    {
        if ($dbType === 'sqlite') {
            return "CREATE TABLE IF NOT EXISTS oauth_clients (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                client_id TEXT NOT NULL UNIQUE,
                client_secret TEXT NOT NULL,
                redirect_uri TEXT NOT NULL,
                grant_types TEXT,
                scope TEXT,
                status INTEGER NOT NULL DEFAULT 1,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )";
        } else {
            return "CREATE TABLE IF NOT EXISTS oauth_clients (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT NOT NULL,
                name VARCHAR(255) NOT NULL,
                client_id VARCHAR(255) NOT NULL UNIQUE,
                client_secret VARCHAR(255) NOT NULL,
                redirect_uri TEXT NOT NULL,
                grant_types TEXT,
                scope TEXT,
                status TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )";
        }
    }

    /**
     * 获取 OAuth 授权码表 SQL
     */
    private function getOAuthAuthorizationCodesTableSql(string $dbType): string
    {
        if ($dbType === 'sqlite') {
            return "CREATE TABLE IF NOT EXISTS oauth_authorization_codes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                authorization_code TEXT NOT NULL UNIQUE,
                client_id TEXT NOT NULL,
                user_id INTEGER NOT NULL,
                redirect_uri TEXT NOT NULL,
                scope TEXT,
                expires_at TEXT NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )";
        } else {
            return "CREATE TABLE IF NOT EXISTS oauth_authorization_codes (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                authorization_code VARCHAR(255) NOT NULL UNIQUE,
                client_id VARCHAR(255) NOT NULL,
                user_id BIGINT NOT NULL,
                redirect_uri TEXT NOT NULL,
                scope TEXT,
                expires_at TIMESTAMP NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )";
        }
    }

    /**
     * 获取 OAuth 令牌表 SQL
     */
    private function getOAuthTokensTableSql(string $dbType): string
    {
        if ($dbType === 'sqlite') {
            return "CREATE TABLE IF NOT EXISTS oauth_tokens (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                access_token TEXT NOT NULL UNIQUE,
                refresh_token TEXT UNIQUE,
                client_id TEXT NOT NULL,
                user_id INTEGER NOT NULL,
                scope TEXT,
                expires_at TEXT NOT NULL,
                refresh_expires_at TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )";
        } else {
            return "CREATE TABLE IF NOT EXISTS oauth_tokens (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                access_token TEXT NOT NULL,
                refresh_token TEXT,
                client_id VARCHAR(255) NOT NULL,
                user_id BIGINT NOT NULL,
                scope TEXT,
                expires_at TIMESTAMP NOT NULL,
                refresh_expires_at TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_access_token (access_token(255)),
                INDEX idx_refresh_token (refresh_token(255))
            )";
        }
    }

    /**
     * 获取 OAuth 权限范围表 SQL
     */
    private function getOAuthScopesTableSql(string $dbType): string
    {
        if ($dbType === 'sqlite') {
            return "CREATE TABLE IF NOT EXISTS oauth_scopes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                scope TEXT NOT NULL UNIQUE,
                description TEXT,
                is_default INTEGER NOT NULL DEFAULT 0,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )";
        } else {
            return "CREATE TABLE IF NOT EXISTS oauth_scopes (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                scope VARCHAR(255) NOT NULL UNIQUE,
                description TEXT,
                is_default TINYINT(1) NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )";
        }
    }

    /**
     * 插入默认权限范围
     */
    private function insertDefaultScopes(\PDO $pdo): void
    {
        $scopes = [
            ['scope' => 'profile', 'description' => '访问基本用户信息'],
            ['scope' => 'email', 'description' => '访问用户邮箱'],
            ['scope' => 'openid', 'description' => 'OpenID Connect'],
        ];

        // 检测数据库类型
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        
        foreach ($scopes as $scope) {
            try {
                if ($driver === 'sqlite') {
                    // SQLite 使用 INSERT OR IGNORE
                    $stmt = $pdo->prepare("INSERT OR IGNORE INTO oauth_scopes (scope, description) VALUES (?, ?)");
                } else {
                    // MySQL/PostgreSQL 使用 INSERT IGNORE 或 ON CONFLICT
                    if ($driver === 'pgsql') {
                        $stmt = $pdo->prepare("INSERT INTO oauth_scopes (scope, description) VALUES (?, ?) ON CONFLICT (scope) DO NOTHING");
                    } else {
                        $stmt = $pdo->prepare("INSERT IGNORE INTO oauth_scopes (scope, description) VALUES (?, ?)");
                    }
                }
                $stmt->execute([$scope['scope'], $scope['description']]);
            } catch (\Exception $e) {
                // 忽略重复键错误
                if (strpos($e->getMessage(), 'UNIQUE constraint') === false && 
                    strpos($e->getMessage(), 'Duplicate entry') === false) {
                    throw $e;
                }
            }
        }
    }

    /**
     * 创建管理员账户
     */
    private function createAdminUser(\PDO $pdo, string $username, string $email, string $password): void
    {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $now = date('Y-m-d H:i:s');
        
        $stmt = $pdo->prepare(
            "INSERT INTO users (username, email, password, name, is_admin, status, created_at, updated_at) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        
        $stmt->execute([
            $username,
            $email,
            $hashedPassword,
            'Administrator',
            1, // is_admin = 1
            1, // status = 1 (启用)
            $now,
            $now
        ]);
    }

    /**
     * 创建锁定文件
     */
    private function createLockFile(): void
    {
        $lockPath = base_path() . '/install.lock';
        $content = date('Y-m-d H:i:s');
        
        if (file_put_contents($lockPath, $content) === false) {
            throw new \Exception('无法创建锁定文件');
        }
    }
}
